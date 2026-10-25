"use client";

import { Plus, ShoppingCart } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import type { Product } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";
import { useCart } from "@/stores/cart";
import { useUi } from "@/stores/ui";

type Bundle = Product["bundles"][number];

/** "Buy together and save". The discount itself is applied by the server quote when all items are in the cart. */
export function BundleOffer({ bundle }: { bundle: Bundle }) {
  const t = useTranslations("bundle");
  const locale = useLocale();
  const add = useCart((s) => s.add);
  const openCart = useUi((s) => s.setCartOpen);
  // Variable products need a size/colour first, so those bundles link to the product instead.
  const canAddAll = bundle.items.every((i) => i.product.type === "simple" && i.product.stock >= i.quantity);

  const addAll = () => {
    track("AddToCart", {
      items: bundle.items.map(({ product, quantity }) => ({ productId: product.id, name: product.name ?? product.slug, price: product.price, quantity })),
    });
    for (const { product, quantity } of bundle.items) {
      add(
        { productId: product.id, variantId: null, slug: product.slug, name: product.name ?? product.slug, variantLabel: null, image: product.image, price: product.price, maxQuantity: product.stock },
        quantity,
      );
    }
    openCart(true);
  };

  return (
    <div className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="text-xl font-semibold">{bundle.name}</h3>
        <span className="rounded-full bg-gold/15 px-3 py-1 text-xs font-bold text-gold tabular-nums">{t("save", { amount: formatTaka(bundle.saving, locale) })}</span>
      </div>
      <ul className="flex flex-wrap items-center gap-2 sm:gap-3">
        {bundle.items.map(({ product, quantity }, i) => (
          <li key={product.id} className="flex items-center gap-2 sm:gap-3">
            {i > 0 && <Plus className="size-4 text-muted-foreground" aria-hidden />}
            <Link href={`/p/${product.slug}`} className="group flex w-24 flex-col gap-1.5 sm:w-28">
              <span className="relative aspect-square overflow-hidden rounded-md bg-placeholder">
                {product.image && <Image src={product.image} alt="" fill sizes="112px" className="object-cover transition-transform duration-500 group-hover:scale-[1.04]" />}
                {quantity > 1 && (
                  <span className="absolute top-1 right-1 rounded-full bg-foreground px-1.5 text-[11px] leading-5 font-semibold text-background tabular-nums">×{formatNumber(quantity, locale)}</span>
                )}
              </span>
              <span className="line-clamp-2 text-xs leading-tight font-medium group-hover:text-primary">{product.name}</span>
            </Link>
          </li>
        ))}
      </ul>
      <div className="flex flex-wrap items-center justify-between gap-4 border-t border-border pt-4">
        <span className="flex items-baseline gap-2 tabular-nums">
          <strong className="text-2xl font-semibold text-primary">{formatTaka(bundle.price, locale)}</strong>
          <s className="text-muted-foreground">{formatTaka(bundle.regular_price, locale)}</s>
        </span>
        {canAddAll ? (
          <button type="button" onClick={addAll} className={cn(buttonVariants(), "h-11 gap-2 rounded-full px-5")}>
            <ShoppingCart className="size-4" strokeWidth={1.9} />
            {t("addAll")}
          </button>
        ) : (
          <p className="text-sm text-muted-foreground">{t("chooseOptions")}</p>
        )}
      </div>
    </div>
  );
}
