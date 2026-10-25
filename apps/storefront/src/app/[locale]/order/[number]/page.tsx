import type { Metadata } from "next";
import Image from "next/image";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { OrderSuccessMark } from "@/components/checkout/order-success-mark";
import { OrderTimeline } from "@/components/order/order-timeline";
import { TrackPurchase } from "@/components/tracking/track-purchase";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { getOrder } from "@/lib/api/checkout";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

export async function generateMetadata({ params }: PageProps<"/[locale]/order/[number]">): Promise<Metadata> {
  const [{ number }, t] = await Promise.all([params, getTranslations("order")]);
  return { title: t("metaTitle", { number }), robots: { index: false } };
}

export default function OrderPage(props: PageProps<"/[locale]/order/[number]">) {
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 px-4 pt-10 pb-16 md:pt-16">
      <Suspense fallback={<div className="h-[480px] animate-pulse rounded-lg bg-card" aria-hidden />}>
        <OrderContent {...props} />
      </Suspense>
    </main>
  );
}

async function OrderContent({ params, searchParams }: PageProps<"/[locale]/order/[number]">) {
  const [{ number }, query, locale] = await Promise.all([params, searchParams, rootLocale()]);
  const token = typeof query.token === "string" ? query.token : "";
  // Arrived from the track-order form rather than straight after checkout.
  const tracking = query.from === "track";
  const [t, tc, tk] = await Promise.all([getTranslations("order"), getTranslations("cart"), getTranslations("checkout")]);
  const order = token ? await getOrder(number, token, locale) : null;

  if (!order) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-lg bg-card px-6 py-16 text-center">
        <h1 className="font-heading text-2xl font-semibold">{t("notFound")}</h1>
        <p className="text-muted-foreground">{t("notFoundNote")}</p>
        <Link href="/" className={cn(buttonVariants(), "mt-2 h-11 rounded-full px-6")}>
          {t("continue")}
        </Link>
      </div>
    );
  }

  return (
    <>
      {tracking ? (
        <section className="flex flex-col items-center gap-3 rounded-lg bg-card px-6 py-8 text-center">
          <span className="text-xs text-muted-foreground">{t("number")}</span>
          <h1 className="text-3xl font-semibold tracking-wide tabular-nums">{order.number}</h1>
          <span className="rounded-full bg-secondary px-3 py-1 text-sm font-semibold text-primary">{t(`statuses.${order.status}`)}</span>
        </section>
      ) : (
        <section className="flex flex-col items-center gap-4 rounded-lg bg-card px-6 py-10 text-center">
          <OrderSuccessMark />
          <div className="flex flex-col gap-1">
            <h1 className="font-heading text-3xl font-semibold tracking-tight">{t("thanks", { name: order.customer_name })}</h1>
            <p className="text-muted-foreground">{t("received")}</p>
          </div>
          <div className="flex flex-col items-center gap-1 rounded-lg bg-background px-6 py-3">
            <span className="text-xs text-muted-foreground">{t("number")}</span>
            <strong className="text-2xl tracking-wide tabular-nums">{order.number}</strong>
          </div>
          <p className="max-w-md text-sm text-muted-foreground">{t("callNote", { phone: order.customer_phone })}</p>
          {order.eta_min_days !== null && order.eta_max_days !== null && (
            <p className="text-sm font-medium text-primary">
              {t("eta", { from: formatNumber(order.eta_min_days, locale), to: formatNumber(order.eta_max_days, locale) })}
            </p>
          )}
        </section>
      )}

      {!tracking && order.number && (
        <TrackPurchase
          number={order.number}
          eventId={order.event_id ?? order.number}
          total={order.total}
          shipping={order.delivery_charge}
          items={order.items.map((item) => ({
            productId: item.product_id ?? 0,
            name: item.name,
            variant: item.variant_label,
            price: item.unit_price,
            quantity: item.quantity,
          }))}
        />
      )}
      <OrderTimeline order={order} />

      <section className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
        <h2 className="font-heading text-xl font-semibold">{t("items")}</h2>
        <ul className="flex flex-col gap-3">
          {order.items.map((item, i) => (
            <li key={i} className="flex items-center gap-3">
              <span className="relative size-14 shrink-0 overflow-hidden rounded-md bg-placeholder">
                {item.image && <Image src={item.image} alt="" fill sizes="56px" className="object-cover" />}
              </span>
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">{item.name}</span>
                <span className="text-xs text-muted-foreground tabular-nums">
                  {[item.variant_label, `${formatTaka(item.unit_price, locale)} × ${formatNumber(item.quantity, locale)}`].filter(Boolean).join(" · ")}
                </span>
              </span>
              <span className="text-sm font-medium tabular-nums">{formatTaka(item.line_total, locale)}</span>
            </li>
          ))}
        </ul>
        <dl className="flex flex-col gap-2.5 border-t border-border pt-4 text-[15px]">
          <div className="flex justify-between">
            <dt className="text-muted-foreground">{tc("subtotal")}</dt>
            <dd className="tabular-nums">{formatTaka(order.subtotal, locale)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-muted-foreground">{tc("delivery")}</dt>
            <dd className="tabular-nums">{order.delivery_charge === 0 ? tc("free") : formatTaka(order.delivery_charge, locale)}</dd>
          </div>
          <div className="flex items-baseline justify-between border-t border-border pt-3">
            <dt className="font-semibold">{tc("total")}</dt>
            <dd className="text-xl font-semibold text-primary tabular-nums">{formatTaka(order.total, locale)}</dd>
          </div>
        </dl>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5 rounded-lg bg-card p-5 text-sm">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{t("shipTo")}</h2>
          <p className="font-medium">{order.customer_name}</p>
          <p className="tabular-nums">{order.customer_phone}</p>
          <p className="text-muted-foreground">{[order.address, order.thana, order.district].filter(Boolean).join(", ")}</p>
        </div>
        <div className="flex flex-col gap-1.5 rounded-lg bg-card p-5 text-sm">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{t("payment")}</h2>
          <p className="flex flex-wrap items-center gap-2 font-medium">
            {tk(`methods.${order.payment_method}`)}
            {order.payment_status !== "unpaid" && (
              <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", order.payment_status === "pending" ? "bg-gold/15 text-gold" : "bg-secondary text-primary")}>
                {t(`paymentStatuses.${order.payment_status}`)}
              </span>
            )}
          </p>
          {order.advance_amount > 0 && order.payment_wallet && (
            <p className="text-muted-foreground">
              {t("sentAdvance", { amount: formatTaka(order.advance_amount, locale), wallet: tk(`methods.${order.payment_wallet}`) })}
              {order.payment_trx_id && <span className="block font-mono text-xs tracking-wide">{t("trx", { trx: order.payment_trx_id })}</span>}
            </p>
          )}
          <p className="text-muted-foreground">{order.due_amount > 0
              ? t("payOnDelivery", { amount: formatTaka(order.due_amount, locale) })
              : order.payment_status === "pending"
                ? t("nothingDueAfterCheck")
                : t("paidInFull")}</p>
        </div>
      </section>

      <Link href="/shop" className={cn(buttonVariants({ size: "lg" }), "h-12 self-center rounded-full px-8 text-[15px]")}>
        {t("continue")}
      </Link>
    </>
  );
}
