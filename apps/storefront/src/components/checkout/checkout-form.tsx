"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Gift, Loader2, LockKeyhole, PenLine, Truck } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { CouponBox } from "@/components/cart/coupon-box";
import { SummaryRows } from "@/components/cart/summary-rows";
import { buttonVariants } from "@/components/ui/button";
import { useCartQuote } from "@/hooks/use-cart-quote";
import { CUSTOMER_KEY, useCustomer } from "@/hooks/use-customer";
import { RECOVER_KEY } from "./recover-cart";
import { redeemablePoints } from "@/lib/rewards";
import { useHydrated } from "@/hooks/use-hydrated";
import { Link, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api/client";
import { placeOrder, saveDraft } from "@/lib/api/cart";
import { placeCustomerOrder } from "@/lib/api/customer";
import type { CartItemInput, CheckoutOptions, CustomerAddress, PlaceOrderInput, Quote, RecoveredCart } from "@/lib/api/types";
import { readAttribution } from "@/lib/attribution";
import { formatNumber, formatTaka } from "@/lib/format";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { readGaIds, track } from "@/lib/tracking";
import { cn } from "@/lib/utils";
import { type CartLine, cartSubtotal, toCartItems, useCart } from "@/stores/cart";
import { Field, inputClass, selectClass, selectStyle } from "./field";
import { advanceFor, type PaymentErrors, PaymentSection, type PaymentValues } from "./payment-section";

type Values = { name: string; phone: string; district_id: string; thana_id: string; address: string; note: string };

type Props = {
  options: CheckoutOptions;
  /** Landing page mode: sell these lines instead of the cart (no coupon box, cart left untouched). */
  lines?: CartLine[];
  landingPageId?: number;
  /** Landing page order-form package (its index), priced by the server. */
  packageIndex?: number | null;
  /** Rendered above the contact fields, e.g. the landing page's variant and quantity picker. */
  before?: ReactNode;
};

/** One-page guest checkout (cash on delivery or manual mobile-wallet payment). Totals always come from the server quote. */
export function CheckoutForm({ options, lines: ownLines, landingPageId, packageIndex = null, before }: Props) {
  const t = useTranslations("checkout");
  const tc = useTranslations("cart");
  const locale = useLocale();
  const router = useRouter();
  const queryClient = useQueryClient();
  const hydrated = useHydrated();
  const cartLines = useCart((s) => s.lines);
  const clearCart = useCart((s) => s.clear);
  const landing = ownLines !== undefined;
  const lines = ownLines ?? cartLines;

  // One id per checkout visit, made on first submit (not at prerender): de-duplicates retries
  // on the server and is shared with pixel/CAPI events.
  const eventId = useRef<string | null>(null);
  const [placed, setPlaced] = useState(false);
  const checkoutTracked = useRef(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [payment, setPayment] = useState<PaymentValues>(() => ({
    method: (options.payment_methods[0]?.code ?? "cod") as PaymentValues["method"],
    wallet: options.payment_methods.find((m) => m.code !== "cod")?.code ?? "",
    sender: "",
    trxId: "",
  }));
  const [paymentErrors, setPaymentErrors] = useState<PaymentErrors>({});

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(2, t("errors.name")).max(100, t("errors.name")),
        phone: z.string().refine((v) => BD_PHONE.test(normalizePhone(v)), t("errors.phone")),
        district_id: z.string().min(1, t("errors.district")),
        thana_id: z.string(),
        address: z.string().trim().min(8, t("errors.address")).max(500, t("errors.address")),
        note: z.string().max(500),
      }),
    [t],
  );

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", phone: "", district_id: "", thana_id: "", address: "", note: "" },
    mode: "onTouched",
  });
  const { register, handleSubmit, control, setError, setValue, formState } = form;

  const watched = useWatch({ control });
  const districtId = Number(watched.district_id) || null;
  const district = options.divisions.flatMap((d) => d.districts).find((d) => d.id === districtId);
  const zone = options.zones.find((z) => z.id === district?.zone_id);
  const thanas = district?.thanas ?? [];

  // Signed-in customers start with their details and default address; chips switch between saved addresses.
  const tAccount = useTranslations("account");
  const { data: customer } = useCustomer();
  const prefilled = useRef(false);
  const applyAddress = useCallback(
    (a: CustomerAddress) => {
      const opts = { shouldValidate: formState.isSubmitted };
      setValue("name", a.name, opts);
      setValue("phone", a.phone, opts);
      setValue("district_id", String(a.district_id), opts);
      setValue("thana_id", a.thana_id ? String(a.thana_id) : "", opts);
      setValue("address", a.address, opts);
    },
    [setValue, formState.isSubmitted],
  );
  // Back from a cart-reminder SMS link: its details win over the account's.
  useEffect(() => {
    try {
      const saved = JSON.parse(sessionStorage.getItem(RECOVER_KEY) ?? "null") as RecoveredCart | null;
      if (!saved) return;
      sessionStorage.removeItem(RECOVER_KEY);
      prefilled.current = true;
      if (saved.name) setValue("name", saved.name);
      setValue("phone", saved.phone);
      if (saved.district_id) setValue("district_id", String(saved.district_id));
      if (saved.thana_id) setValue("thana_id", String(saved.thana_id));
      if (saved.address) setValue("address", saved.address);
    } catch {
      // Storage blocked or malformed; nothing to restore.
    }
  }, [setValue]);
  useEffect(() => {
    if (!customer || prefilled.current) return;
    prefilled.current = true;
    const values = form.getValues();
    if (values.name || values.phone || values.address) return;
    const fallback = customer.addresses.find((a) => a.is_default) ?? customer.addresses[0];
    if (fallback) return applyAddress(fallback);
    if (customer.name) setValue("name", customer.name);
    setValue("phone", customer.phone);
  }, [customer, form, setValue, applyAddress]);
  // Order bumps the customer ticked; kept here (not in the cart) and sent as extra quote/order lines.
  const [bumps, setBumps] = useState<Record<number, Quote["bumps"][number]>>({});
  const bumpItems: CartItemInput[] = Object.values(bumps).map((b) => ({ product_id: b.product_id, quantity: 1, bump_id: b.id }));
  const [usePoints, setUsePoints] = useState(false);
  const [giftWrap, setGiftWrap] = useState(false);
  const [giftNoteOpen, setGiftNoteOpen] = useState(false);
  const [giftNote, setGiftNote] = useState("");
  const storedCoupon = useCart((s) => s.coupon);
  const coupon = landing ? null : storedCoupon;
  const landingQuote = useMemo(() => (landingPageId ? { landing_page_id: landingPageId, package: packageIndex } : null), [landingPageId, packageIndex]);
  const { data: quote, refetch, isFetching } = useCartQuote(districtId, bumpItems, ownLines, landingQuote);
  const offers = [...Object.values(bumps), ...(quote?.bumps ?? []).filter((b) => !bumps[b.id])];

  const mutation = useMutation({
    // `transfer`: this order sends money by wallet first, so the payment fields go out.
    // `redeem`: points to spend; those orders go through the signed-in route.
    mutationFn: ({ values, transfer, redeem }: { values: Values; transfer: boolean; redeem: number }) =>
      (redeem > 0 ? placeCustomerOrder : placeOrder)(
        {
          name: values.name.trim(),
          phone: normalizePhone(values.phone),
          district_id: Number(values.district_id),
          thana_id: values.thana_id ? Number(values.thana_id) : null,
          address: values.address.trim(),
          note: values.note.trim() || null,
          gift_wrap: options.gift.wrap && giftWrap,
          gift_note: options.gift.note && giftNoteOpen ? giftNote.trim() || null : null,
          payment_method: payment.method,
          ...(transfer
            ? {
                payment_wallet: payment.method === "cod" ? (payment.wallet as PlaceOrderInput["payment_wallet"]) : null,
                payment_sender: normalizePhone(payment.sender),
                payment_trx_id: payment.trxId,
              }
            : {}),
          items: [...toCartItems(lines), ...bumpItems],
          coupon_code: coupon,
          event_id: (eventId.current ??= crypto.randomUUID()),
          landing_page_id: landingPageId ?? null,
          package: packageIndex,
          ...readAttribution(),
          ...readGaIds(),
          ...(redeem > 0 ? { redeem_points: redeem } : {}),
        },
        locale,
      ),
    onSuccess: ({ data, access_token }, { redeem }) => {
      setPlaced(true);
      if (redeem > 0) queryClient.invalidateQueries({ queryKey: CUSTOMER_KEY });
      try {
        sessionStorage.removeItem("lj-draft");
      } catch {}
      router.replace(`/order/${data.number}?token=${access_token}`);
      if (!landing) clearCart();
    },
    onError: (error) => {
      if (!(error instanceof ApiError)) return setFormError(t("errors.generic"));
      if (error.status === 429) return setFormError(t("errors.tooMany"));
      if (error.status !== 422) return setFormError(t("errors.generic"));

      const fields = error.fieldErrors;
      const map = { name: "name", phone: "phone", district_id: "district", thana_id: "thana", address: "address" } as const;
      for (const [field, key] of Object.entries(map)) {
        if (fields[field]) setError(field as keyof Values, { message: t(`errors.${key}`) });
      }
      const pay: PaymentErrors = {};
      if (fields.payment_method) pay.method = t("errors.method");
      if (fields.payment_wallet) pay.wallet = t("errors.wallet");
      if (fields.payment_sender) pay.sender = t("errors.sender");
      // The format was checked before sending, so a rejected ID is one already used.
      if (fields.payment_trx_id) pay.trxId = t("errors.trxUsed");
      setPaymentErrors(pay);
      if (fields.redeem_points) {
        setUsePoints(false);
        setFormError(t("errors.points"));
        queryClient.invalidateQueries({ queryKey: CUSTOMER_KEY });
      }
      if (fields.coupon_code) {
        setFormError(t("errors.coupon"));
        queryClient.invalidateQueries({ queryKey: ["cart-quote"] });
      }
      if (Object.keys(fields).some((f) => f.startsWith("items"))) {
        setFormError(t("errors.stock"));
        queryClient.invalidateQueries({ queryKey: ["cart-quote"] });
        refetch();
      }
    },
  });

  // Incomplete-order capture: once the phone is valid, save what's typed (debounced) so staff can call back.
  const draftId = useRef<string | null>(null);
  const lastDraft = useRef("");
  useEffect(() => {
    if (!options.capture_incomplete || placed || lines.length === 0) return;
    const phone = normalizePhone(watched.phone ?? "");
    if (!BD_PHONE.test(phone)) return;

    const input = {
      draft_id: (draftId.current ??= readDraftId()),
      phone,
      name: watched.name?.trim() || null,
      district_id: Number(watched.district_id) || null,
      thana_id: Number(watched.thana_id) || null,
      address: watched.address?.trim() || null,
      note: watched.note?.trim() || null,
      items: toCartItems(lines),
      utm: readAttribution().utm,
    };
    const key = JSON.stringify(input);
    if (key === lastDraft.current) return;

    const timer = setTimeout(() => {
      lastDraft.current = key;
      saveDraft(input, locale);
    }, 1500);
    return () => clearTimeout(timer);
  }, [watched, lines, placed, locale, options.capture_incomplete]);

  const trackCheckout = useCallback(() => {
    if (checkoutTracked.current || lines.length === 0) return;
    checkoutTracked.current = true;
    track("InitiateCheckout", {
      items: lines.map((l) => ({ productId: l.productId, variantId: l.variantId, name: l.name, variant: l.variantLabel, price: l.price, quantity: l.quantity })),
    });
  }, [lines]);

  // The checkout page counts as starting checkout; on a landing page that's the first tap into the form.
  useEffect(() => {
    if (hydrated && !landing) trackCheckout();
  }, [hydrated, landing, trackCheckout]);

  if (!hydrated) return <div className="h-96 animate-pulse rounded-lg bg-card" aria-hidden />;

  if (lines.length === 0 && !placed) {
    return (
      <div className="flex flex-col items-center gap-4 rounded-lg bg-card px-6 py-16 text-center">
        <p className="text-muted-foreground">{t("emptyCart")}</p>
        <Link href="/shop" className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {tc("startShopping")}
        </Link>
      </div>
    );
  }

  const subtotal = quote?.subtotal ?? cartSubtotal(lines);
  const deliveryCharge = quote ? quote.delivery_charge : null;
  // Points: worked out here for display; the API checks them again when the order is placed.
  const pointsRules = customer?.rewards.points ?? null;
  const ownNumber = !!customer && normalizePhone(watched.phone ?? "") === customer.phone;
  const redeemable = pointsRules && quote ? redeemablePoints(pointsRules, quote.subtotal - quote.discount_total) : 0;
  const redeem = usePoints && ownNumber && !landing ? redeemable : 0;
  const pointsDiscount = redeem * (pointsRules?.point_value ?? 0);
  const discounts = [...(quote?.discounts ?? []), ...(redeem > 0 ? [{ type: "points" as const, label: formatNumber(redeem, locale), amount: pointsDiscount }] : [])];
  const wrapCharge = options.gift.wrap && giftWrap ? options.gift.wrap_price : 0;
  const total = (quote ? quote.total : subtotal + (deliveryCharge ?? 0)) - pointsDiscount + wrapCharge;
  const bumpLines = quote?.lines.filter((l) => l.bump_id) ?? [];
  const blocked = quote?.has_problems ?? false;
  const busy = mutation.isPending || placed;

  return (
    <form
      noValidate
      onSubmit={handleSubmit((values) => {
        setFormError(null);
        if (thanas.length > 0 && !values.thana_id) return setError("thana_id", { message: t("errors.thana") });
        const amount = advanceFor(options, payment.method, total, deliveryCharge);
        const transfer = (amount ?? 0) > 0;
        const pay: PaymentErrors = {};
        if (transfer) {
          if (payment.method === "cod" && !payment.wallet) pay.wallet = t("errors.wallet");
          if (!BD_PHONE.test(normalizePhone(payment.sender))) pay.sender = t("errors.sender");
          if (!/^[A-Z0-9]{6,30}$/.test(payment.trxId)) pay.trxId = t("errors.trx");
        }
        setPaymentErrors(pay);
        if (Object.keys(pay).length > 0) return;
        mutation.mutate({ values, transfer, redeem });
      })}
      onFocusCapture={landing ? trackCheckout : undefined}
      className="grid items-start gap-6 lg:grid-cols-[1fr_400px] lg:gap-10"
    >
      <div className="flex flex-col gap-6">
        {before}
        <section className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
          <h2 className="font-heading text-xl font-semibold">{t("contact")}</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label={t("name")} error={formState.errors.name?.message}>
              {(props) => <input {...props} {...register("name")} autoComplete="name" placeholder={t("namePlaceholder")} className={inputClass} />}
            </Field>
            <Field label={t("phone")} error={formState.errors.phone?.message}>
              {(props) => (
                <input
                  {...props}
                  {...register("phone")}
                  type="tel"
                  inputMode="tel"
                  autoComplete="tel"
                  placeholder={t("phonePlaceholder")}
                  className={cn(inputClass, "tabular-nums")}
                />
              )}
            </Field>
          </div>
        </section>

        <section className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
          <h2 className="font-heading text-xl font-semibold">{t("address")}</h2>
          {customer && customer.addresses.length > 1 && (
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium">{tAccount("savedAddresses")}</span>
              <div className="flex flex-wrap gap-2">
                {customer.addresses.map((a) => {
                  const active = watched.address === a.address && watched.district_id === String(a.district_id);
                  return (
                    <button
                      key={a.id}
                      type="button"
                      onClick={() => applyAddress(a)}
                      aria-pressed={active}
                      className={cn(
                        "rounded-full border px-3.5 py-1.5 text-sm transition-colors",
                        active ? "border-primary bg-primary/5 font-semibold text-primary" : "border-border hover:border-primary/50",
                      )}
                    >
                      {a.label || a.district}
                    </button>
                  );
                })}
              </div>
            </div>
          )}
          <Field label={t("district")} error={formState.errors.district_id?.message}>
            {(props) => (
              <select {...props} {...register("district_id", { onChange: () => setValue("thana_id", "") })} className={selectClass} style={selectStyle}>
                <option value="">{t("districtPlaceholder")}</option>
                {options.divisions.map((division) => (
                  <optgroup key={division.name} label={division.name}>
                    {division.districts.map((d) => (
                      <option key={d.id} value={d.id}>
                        {d.name}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            )}
          </Field>
          {zone && (
            <p className="-mt-2 flex items-center gap-2 text-sm text-primary">
              <Truck className="size-4 shrink-0" strokeWidth={1.8} />
              {t("deliveryTo", { zone: zone.name, from: formatNumber(zone.eta_min_days, locale), to: formatNumber(zone.eta_max_days, locale) })}
            </p>
          )}
          {thanas.length > 0 && (
            <Field label={t("thana")} error={formState.errors.thana_id?.message}>
              {(props) => (
                <select {...props} {...register("thana_id")} className={selectClass} style={selectStyle}>
                  <option value="">{t("thanaPlaceholder")}</option>
                  {thanas.map((th) => (
                    <option key={th.id} value={th.id}>
                      {th.name}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          )}
          <Field label={t("fullAddress")} error={formState.errors.address?.message}>
            {(props) => (
              <textarea {...props} {...register("address")} rows={3} autoComplete="street-address" placeholder={t("fullAddressPlaceholder")} className={cn(inputClass, "h-auto resize-none py-3")} />
            )}
          </Field>
          <Field label={t("note")}>
            {(props) => <input {...props} {...register("note")} placeholder={t("notePlaceholder")} className={inputClass} />}
          </Field>
          {(options.gift.wrap || options.gift.note) && (
            <div className="flex flex-col gap-3 rounded-lg border border-border p-4">
              {options.gift.wrap && (
                <label className="flex cursor-pointer items-center gap-3 text-[15px]">
                  <input type="checkbox" checked={giftWrap} onChange={(e) => setGiftWrap(e.target.checked)} className="size-4 accent-primary" />
                  <Gift className="size-4 text-primary" strokeWidth={1.8} />
                  <span className="flex-1">{t("giftWrap")}</span>
                  <span className="text-sm text-muted-foreground tabular-nums">
                    {options.gift.wrap_price > 0 ? `+${formatTaka(options.gift.wrap_price, locale)}` : t("giftWrapFree")}
                  </span>
                </label>
              )}
              {options.gift.note && (
                <label className="flex cursor-pointer items-center gap-3 text-[15px]">
                  <input type="checkbox" checked={giftNoteOpen} onChange={(e) => setGiftNoteOpen(e.target.checked)} className="size-4 accent-primary" />
                  <PenLine className="size-4 text-primary" strokeWidth={1.8} />
                  {t("giftNote")}
                </label>
              )}
              {options.gift.note && giftNoteOpen && (
                <textarea
                  value={giftNote}
                  onChange={(e) => setGiftNote(e.target.value)}
                  maxLength={300}
                  rows={2}
                  aria-label={t("giftNote")}
                  placeholder={t("giftNotePlaceholder")}
                  className={cn(inputClass, "h-auto resize-none py-3")}
                />
              )}
            </div>
          )}
        </section>

        <PaymentSection
          options={options}
          value={payment}
          onChange={(next) => {
            // A field's error goes away once the customer changes it.
            const keys = (Object.keys(next) as (keyof PaymentValues)[]).filter((k) => next[k] !== payment[k]);
            if (keys.length) setPaymentErrors((e) => Object.fromEntries(Object.entries(e).filter(([k]) => !keys.includes(k as keyof PaymentValues))));
            setPayment(next);
          }}
          errors={paymentErrors}
          amount={advanceFor(options, payment.method, total, deliveryCharge)}
          total={total}
        />

        {offers.length > 0 && (
          <section className="flex flex-col gap-3 rounded-lg bg-card p-5 sm:p-6">
            <h2 className="font-heading text-xl font-semibold">{t("bumpTitle")}</h2>
            {offers.map((offer) => {
              const checked = Boolean(bumps[offer.id]);
              return (
                <label
                  key={offer.id}
                  className={cn(
                    "flex cursor-pointer items-center gap-4 rounded-lg border-2 border-dashed p-3.5 transition-colors",
                    checked ? "border-primary bg-secondary/60" : "border-gold/60 hover:border-gold",
                  )}
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={(e) =>
                      setBumps((current) => {
                        const next = { ...current };
                        if (e.target.checked) next[offer.id] = offer;
                        else delete next[offer.id];
                        return next;
                      })
                    }
                    className="size-5 shrink-0 accent-primary"
                  />
                  <span className="relative size-14 shrink-0 overflow-hidden rounded-md bg-placeholder">
                    {offer.image && <Image src={offer.image} alt="" fill sizes="56px" className="object-cover" />}
                  </span>
                  <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="font-semibold">{offer.title}</span>
                    {offer.description && <span className="text-sm text-muted-foreground">{offer.description}</span>}
                    <span className="flex items-baseline gap-2 text-sm tabular-nums">
                      <strong className="text-primary">{formatTaka(offer.price, locale)}</strong>
                      {offer.regular_price > offer.price && <s className="text-muted-foreground">{formatTaka(offer.regular_price, locale)}</s>}
                    </span>
                  </span>
                </label>
              );
            })}
          </section>
        )}
      </div>

      <aside className={cn("flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6 lg:sticky", landing ? "lg:top-6" : "lg:top-36")}>
        <h2 className="font-heading text-xl font-semibold">{tc("summary")}</h2>
        <ul className="flex max-h-72 flex-col gap-3 overflow-y-auto" data-lenis-prevent>
          {lines.map((line) => {
            const q = quote?.lines.find((l) => l.product_id === line.productId && (l.variant_id ?? null) === line.variantId);
            return (
              <li key={line.key} className="flex items-center gap-3">
                <span className="relative size-14 shrink-0 overflow-hidden rounded-md bg-placeholder">
                  {line.image && <Image src={line.image} alt="" fill sizes="56px" className="object-cover" />}
                  <span className="absolute top-0.5 right-0.5 min-w-5 rounded-full bg-foreground px-1 text-center text-[11px] leading-5 font-semibold text-background tabular-nums">
                    {formatNumber(line.quantity, locale)}
                  </span>
                </span>
                <span className="flex min-w-0 flex-1 flex-col">
                  <span className="truncate text-sm font-medium">{q?.name ?? line.name}</span>
                  {line.variantLabel && <span className="text-xs text-muted-foreground">{line.variantLabel}</span>}
                  {q?.preorder && <span className="text-xs font-medium text-gold">{t("preorderLine")}</span>}
                  {q?.problem && (
                    <span className="text-xs font-medium text-destructive">
                      {q.problem === "unavailable" ? tc("unavailable") : tc("onlyLeft", { count: formatNumber(q.available, locale) })}
                    </span>
                  )}
                </span>
                <span className="text-sm font-medium tabular-nums">{formatTaka(line.price * line.quantity, locale)}</span>
              </li>
            );
          })}
          {bumpLines.map((l) => (
            <li key={`bump-${l.bump_id}`} className="flex items-center gap-3">
              <span className="relative size-14 shrink-0 overflow-hidden rounded-md bg-placeholder">
                {l.image && <Image src={l.image} alt="" fill sizes="56px" className="object-cover" />}
              </span>
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">{l.name}</span>
                <span className="text-xs font-medium text-gold">{t("bumpTag")}</span>
              </span>
              <span className="text-sm font-medium tabular-nums">{formatTaka(l.line_total, locale)}</span>
            </li>
          ))}
        </ul>
        <SummaryRows subtotal={subtotal} discounts={discounts} deliveryCharge={deliveryCharge} giftWrap={wrapCharge} total={total} />
        {!landing && <CouponBox quote={quote} loading={isFetching} />}
        {!landing && pointsRules && redeemable > 0 && (
          <label className={cn("flex items-start gap-3 rounded-md border border-border p-3 text-sm", !ownNumber && "opacity-60")}>
            <input type="checkbox" checked={usePoints && ownNumber} disabled={!ownNumber} onChange={(e) => setUsePoints(e.target.checked)} className="mt-0.5 size-4 accent-primary" />
            <span className="flex flex-col gap-0.5">
              <span className="font-medium">
                {t("usePoints", { points: formatNumber(redeemable, locale), amount: formatTaka(redeemable * pointsRules.point_value, locale) })}
              </span>
              <span className="text-xs text-muted-foreground">
                {ownNumber ? t("pointsBalance", { points: formatNumber(pointsRules.balance, locale) }) : t("pointsOwnNumber")}
              </span>
            </span>
          </label>
        )}

        {(formError || blocked) && (
          <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {formError ?? tc("fixProblems")}{" "}
            {blocked && !landing && (
              <Link href="/cart" className="font-semibold underline">
                {tc("viewCart")}
              </Link>
            )}
          </p>
        )}

        <button type="submit" disabled={busy || blocked} className={cn(buttonVariants({ size: "lg" }), "h-13 rounded-full text-base")}>
          {busy ? (
            <>
              <Loader2 className="size-4 animate-spin" />
              {t("placing")}
            </>
          ) : (
            <>
              {t("placeOrder")}
              {deliveryCharge !== null && <span className="tabular-nums opacity-80">· {formatTaka(total, locale)}</span>}
            </>
          )}
        </button>
        <p className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
          <LockKeyhole className="size-3.5" strokeWidth={1.8} />
          {t("secure")}
        </p>
        <p className="-mt-3 text-center text-xs text-muted-foreground">{t("agree")}</p>
      </aside>
    </form>
  );
}

/** One draft per browser tab session, so a reload keeps updating the same incomplete order. */
function readDraftId(): string {
  try {
    const existing = sessionStorage.getItem("lj-draft");
    if (existing) return existing;
    const id = crypto.randomUUID();
    sessionStorage.setItem("lj-draft", id);
    return id;
  } catch {
    return crypto.randomUUID();
  }
}
