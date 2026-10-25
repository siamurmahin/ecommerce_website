"use client";

import { Check, Minus, Phone, Plus, Truck } from "lucide-react";
import { AnimatePresence, motion } from "motion/react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { useHydrated } from "@/hooks/use-hydrated";
import { Link, useRouter } from "@/i18n/navigation";
import type { DeliveryZone, Product, SiteSettings } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";
import { useCart } from "@/stores/cart";
import { useUi } from "@/stores/ui";
import { Countdown } from "@/components/commerce/countdown";
import { Stars } from "@/components/reviews/stars";
import { Gallery } from "./gallery";
import { useVariantSelection, VariantOptions } from "./variant-picker";

type Props = {
  product: Product;
  zones: DeliveryZone[];
  /** Quick-view mode: no contact buttons, delivery card or SKU; links to the full page instead. */
  compact?: boolean;
  /** Called before navigating away (lets the quick view close itself). */
  onDone?: () => void;
  /** Store contact for the WhatsApp/call buttons (full page only). */
  contact?: Pick<SiteSettings, "hotline" | "hotline_tel" | "whatsapp_url">;
};

export function ProductPurchase({ product, zones, compact = false, onDone, contact }: Props) {
  const t = useTranslations("product");
  const tc = useTranslations("card");
  const tr = useTranslations("reviews");
  const locale = useLocale();
  const router = useRouter();
  const add = useCart((s) => s.add);
  const openCart = useUi((s) => s.setCartOpen);
  const selection = useVariantSelection(product);
  const { variant, price, compare, stock } = selection;
  const [qty, setQty] = useState(1);
  const [added, setAdded] = useState(false);
  const low = stock > 0 && stock <= product.low_stock_threshold;

  // Once per product shown (full page or quick view); the ref also absorbs Strict Mode's re-run.
  const viewed = useRef<number | null>(null);
  useEffect(() => {
    if (viewed.current === product.id) return;
    viewed.current = product.id;
    track("ViewContent", { items: [{ productId: product.id, name: product.name ?? product.slug, category: product.category?.name, price: product.price, quantity: 1 }] });
  }, [product.id, product.name, product.slug, product.category?.name, product.price]);

  const addToCart = () => {
    track("AddToCart", {
      items: [{ productId: product.id, variantId: variant?.id ?? null, name: product.name ?? product.slug, variant: variant?.label, category: product.category?.name, price, quantity: qty }],
    });
    add(
      {
        productId: product.id,
        variantId: variant?.id ?? null,
        slug: product.slug,
        name: product.name ?? product.slug,
        variantLabel: variant?.label ?? null,
        image: variant?.image ?? product.image,
        price,
        maxQuantity: stock,
      },
      qty,
    );
    setAdded(true);
    setTimeout(() => setAdded(false), 1800);
  };

  // Dates depend on the visitor's clock, so they're only computed in the browser, never at prerender.
  const hydrated = useHydrated();
  const span = deliverySpan(zones);
  const eta = hydrated && span ? deliveryDates(locale, span) : null;
  const highlights = (product.short_description ?? "").split("\n").map((l) => l.trim()).filter(Boolean);

  return (
    <div className="grid gap-8 md:grid-cols-2 md:gap-12">
      <Gallery images={product.images} activeImage={variant?.image} />

      <div className="flex flex-col gap-6">
        <div className="flex flex-col gap-3">
          {product.brand && <span className="text-xs font-bold tracking-[0.14em] text-gold uppercase">{product.brand.name}</span>}
          <h1 className="text-3xl leading-tight font-semibold tracking-tight md:text-4xl">{product.name}</h1>
          {product.reviews_count > 0 && (
            <a href="#reviews" className="flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground">
              <Stars value={product.rating_avg} label={tr("ratingAria", { rating: formatNumber(product.rating_avg, locale) })} />
              <span className="font-semibold text-foreground tabular-nums">{formatNumber(product.rating_avg, locale)}</span>
              <span className="tabular-nums">({tr("count", { count: formatNumber(product.reviews_count, locale) })})</span>
            </a>
          )}
          {product.flash_sale && (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gold/10 px-3.5 py-2.5">
              <span className="text-sm font-bold text-gold">{product.flash_sale.name}</span>
              <Countdown endsAt={product.flash_sale.ends_at} />
            </div>
          )}
          <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 tabular-nums">
            <AnimatePresence mode="popLayout" initial={false}>
              <motion.strong
                key={price}
                initial={{ y: 8, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                exit={{ y: -8, opacity: 0 }}
                className="text-3xl font-semibold text-primary"
              >
                {formatTaka(price, locale)}
              </motion.strong>
            </AnimatePresence>
            {compare && <s className="text-lg text-muted-foreground">{formatTaka(compare, locale)}</s>}
            {compare && (
              <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-primary">
                {t("save", { amount: formatNumber((compare - price) / 100, locale) })}
              </span>
            )}
          </div>
        </div>

        {highlights.length > 0 && (
          <ul className="flex flex-col gap-2 text-[15px]">
            {highlights.map((h) => (
              <li key={h} className="flex gap-2.5">
                <Check className="mt-1 size-4 shrink-0 text-primary" strokeWidth={2.2} />
                {h}
              </li>
            ))}
          </ul>
        )}

        <VariantOptions product={product} selection={selection} />

        <p className={cn("text-sm font-medium", stock === 0 ? "text-destructive" : low ? "text-gold" : "text-primary")}>
          {stock === 0
            ? t("outOfStock")
            : low
              ? t("lowStock", { count: formatNumber(stock, locale) })
              : t("inStock", { count: formatNumber(stock, locale) })}
        </p>

        <div className="flex flex-col gap-3">
          <div className="flex gap-3">
            <div className="flex h-12 items-center rounded-full border border-border bg-card" aria-label={t("quantity")}>
              <button type="button" onClick={() => setQty((q) => Math.max(1, q - 1))} className="grid h-full w-11 place-items-center" aria-label="−">
                <Minus className="size-4" />
              </button>
              <span className="w-8 text-center tabular-nums">{formatNumber(qty, locale)}</span>
              <button type="button" onClick={() => setQty((q) => Math.min(Math.max(stock, 1), q + 1))} className="grid h-full w-11 place-items-center" aria-label="+">
                <Plus className="size-4" />
              </button>
            </div>
            <Button
              size="lg"
              variant="outline"
              disabled={stock === 0}
              onClick={() => {
                addToCart();
                openCart(true);
              }}
              className="h-12 flex-1 rounded-full text-[15px]">
              <AnimatePresence mode="wait" initial={false}>
                <motion.span key={added ? "done" : "add"} initial={{ y: 6, opacity: 0 }} animate={{ y: 0, opacity: 1 }} exit={{ y: -6, opacity: 0 }} className="inline-flex items-center gap-2">
                  {added ? <Check className="size-4" /> : null}
                  {added ? t("added") : t("addToCart")}
                </motion.span>
              </AnimatePresence>
            </Button>
          </div>
          <Button
            size="lg"
            disabled={stock === 0}
            onClick={() => {
              addToCart();
              onDone?.();
              router.push("/checkout");
            }}
            className="h-12 rounded-full text-[15px]"
          >
            {t("buyNow")}
          </Button>
          {!compact && contact && (
          <div className="grid grid-cols-2 gap-3">
            {contact.whatsapp_url && (
              <a
                href={`${contact.whatsapp_url}?text=${encodeURIComponent(product.name ?? "")}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-11 items-center justify-center rounded-full border border-border bg-card text-sm font-medium hover:border-primary"
              >
                {t("whatsapp")}
              </a>
            )}
            {contact.hotline && (
              <a href={`tel:${contact.hotline_tel}`} className="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-border bg-card text-sm font-medium hover:border-primary">
                <Phone className="size-4" strokeWidth={1.8} />
                {contact.hotline}
              </a>
            )}
          </div>
          )}
        </div>

        {compact ? (
          <Link href={`/p/${product.slug}`} onClick={onDone} className="text-sm font-semibold text-primary underline-offset-4 hover:underline">
            {tc("viewDetails")} →
          </Link>
        ) : (
        <div className="flex gap-3 rounded-lg border border-border bg-card p-4 text-sm">
          <Truck className="mt-0.5 size-5 shrink-0 text-primary" strokeWidth={1.7} />
          <div className="flex flex-col gap-1">
            <span className="font-semibold">
              {eta
                ? t("deliveryEstimate", eta)
                : span
                  ? t("deliveryWithin", { from: formatNumber(span.min, locale), to: formatNumber(span.max, locale) })
                  : t("delivery")}
            </span>
            {product.free_delivery ? (
              <span className="font-medium text-primary">{t("freeDelivery")}</span>
            ) : (
              <span className="text-muted-foreground">{zones.map((z) => `${z.name} ${formatTaka(z.charge, locale)}`).join(" · ")}</span>
            )}
          </div>
        </div>

        )}

        {!compact && product.sku && (
          <p className="text-xs text-muted-foreground">
            {t("sku")}: {variant?.sku ?? product.sku}
          </p>
        )}
      </div>
    </div>
  );
}

/** Fastest and slowest delivery across the admin's zones. */
function deliverySpan(zones: DeliveryZone[]) {
  if (zones.length === 0) return null;
  return { min: Math.min(...zones.map((z) => z.eta_min_days)), max: Math.max(...zones.map((z) => z.eta_max_days)) };
}

function deliveryDates(locale: string, span: { min: number; max: number }) {
  const fmt = new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "bn-BD", { day: "numeric", month: "short" });
  const now = Date.now();
  return { from: fmt.format(now + span.min * 864e5), to: fmt.format(now + span.max * 864e5) };
}
