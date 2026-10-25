"use client";

import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useMemo, useRef, useState } from "react";
import { CheckoutForm } from "@/components/checkout/checkout-form";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { useVariantSelection, VariantOptions } from "@/components/product/variant-picker";
import type { CheckoutOptions, Product } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";
import type { CartLine } from "@/stores/cart";

type Props = { product: Product | null; options: CheckoutOptions; landingPageId: number };

/** The landing page's own checkout: pick variant and quantity, then the usual COD form. The cart is never touched. */
export function LandingOrder({ product, options, landingPageId }: Props) {
  const t = useTranslations("landing");
  if (!product) return <p className="rounded-lg bg-card px-6 py-12 text-center text-muted-foreground">{t("unavailable")}</p>;
  return <Order product={product} options={options} landingPageId={landingPageId} />;
}

function Order({ product, options, landingPageId }: { product: Product; options: CheckoutOptions; landingPageId: number }) {
  const t = useTranslations();
  const locale = useLocale();
  const selection = useVariantSelection(product);
  const { variant, price, compare, stock } = selection;
  const [qty, setQty] = useState(1);
  const quantity = Math.max(1, Math.min(qty, stock || 1));

  const lines = useMemo<CartLine[]>(
    () => [
      {
        key: variant ? `${product.id}:${variant.id}` : String(product.id),
        productId: product.id,
        variantId: variant?.id ?? null,
        slug: product.slug,
        name: product.name ?? product.slug,
        variantLabel: variant?.label ?? null,
        image: variant?.image ?? product.image,
        price,
        quantity,
        maxQuantity: Math.max(stock, 1),
      },
    ],
    [product, variant, price, quantity, stock],
  );

  // A landing visit is a product view; the ref absorbs Strict Mode's re-run.
  const viewed = useRef(false);
  useEffect(() => {
    if (viewed.current) return;
    viewed.current = true;
    track("ViewContent", { items: [{ productId: product.id, name: product.name ?? product.slug, category: product.category?.name, price: product.price, quantity: 1 }] });
  }, [product]);

  const picker = (
    <section className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
      <div className="flex items-center gap-4">
        <span className="relative size-20 shrink-0 overflow-hidden rounded-md bg-placeholder">
          {lines[0].image && <Image src={lines[0].image} alt="" fill sizes="80px" className="object-cover" />}
        </span>
        <span className="flex min-w-0 flex-col gap-1">
          <span className="font-semibold">{product.name}</span>
          <span className="flex flex-wrap items-baseline gap-2 tabular-nums">
            <strong className="text-lg text-primary">{formatTaka(price, locale)}</strong>
            {compare && <s className="text-sm text-muted-foreground">{formatTaka(compare, locale)}</s>}
          </span>
        </span>
      </div>
      <VariantOptions product={product} selection={selection} />
      <div className="flex flex-wrap items-center justify-between gap-3">
        <span className="text-sm font-semibold">{t("product.quantity")}</span>
        <QuantityStepper value={quantity} max={Math.max(stock, 1)} onChange={(v) => setQty(Math.max(1, v))} />
      </div>
      <p className={cn("text-sm font-medium", stock === 0 ? "text-destructive" : "text-primary")}>
        {stock === 0 ? t("product.outOfStock") : t("product.inStock", { count: formatNumber(stock, locale) })}
      </p>
    </section>
  );

  return <CheckoutForm options={options} lines={lines} landingPageId={landingPageId} before={picker} />;
}
