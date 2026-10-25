"use client";

import { Eye, ShoppingCart, Zap } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import type { ProductCard } from "@/lib/api/types";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";
import { useCart } from "@/stores/cart";
import { useUi } from "@/stores/ui";

/** Add to cart + order now. Variable products open quick view so a size/colour can be chosen first. */
export function CardBuyButtons({ product }: { product: ProductCard }) {
  const t = useTranslations("card");
  const router = useRouter();
  const add = useCart((s) => s.add);
  const openCart = useUi((s) => s.setCartOpen);
  const openQuickView = useUi((s) => s.setQuickView);
  const needsChoice = product.price_from || product.type === "variable";

  const addSimple = () => {
    track("AddToCart", { items: [{ productId: product.id, name: product.name ?? product.slug, category: product.category?.name, price: product.price, quantity: 1 }] });
    add({
      productId: product.id,
      variantId: null,
      slug: product.slug,
      name: product.name ?? product.slug,
      variantLabel: null,
      image: product.image,
      price: product.price,
      maxQuantity: product.stock,
    });
  };

  const cartClass =
    "grid size-9 shrink-0 place-items-center rounded-md border border-border bg-card text-foreground transition-[color,border-color,transform] active:scale-95 hover:border-foreground disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-border sm:size-10";

  if (!product.in_stock) {
    return (
      <div className="flex gap-1.5 sm:gap-2">
        <button type="button" disabled aria-label={t("outOfStock")} title={t("outOfStock")} className={cartClass}>
          <ShoppingCart className="size-4" strokeWidth={1.9} />
        </button>
        <span aria-disabled className="flex h-9 flex-1 cursor-not-allowed items-center justify-center rounded-md bg-muted-foreground/45 px-2 text-xs font-bold text-white sm:h-10 sm:text-[13px]">
          {t("outOfStock")}
        </span>
      </div>
    );
  }

  return (
    <div className="flex gap-1.5 sm:gap-2">
      <button
        type="button"
        aria-label={t("addToCart")}
        title={t("addToCart")}
        onClick={() => {
          if (needsChoice) return openQuickView(product.slug);
          addSimple();
          openCart(true);
        }}
        className={cartClass}
      >
        <ShoppingCart className="size-4" strokeWidth={1.9} />
      </button>
      <button
        type="button"
        onClick={() => {
          if (needsChoice) return openQuickView(product.slug);
          addSimple();
          router.push("/checkout");
        }}
        className="flex h-9 min-w-0 flex-1 items-center justify-center gap-1 rounded-md bg-primary px-2 text-xs font-bold text-primary-foreground transition-[background-color,transform] hover:bg-primary/90 active:scale-[0.98] sm:h-10 sm:text-[13px]"
      >
        <Zap className="size-3.5 shrink-0 fill-current" strokeWidth={2} />
        <span className="truncate">{t("orderNow")}</span>
      </button>
    </div>
  );
}

export function QuickViewButton({ slug, className }: { slug: string; className?: string }) {
  const t = useTranslations("card");
  const open = useUi((s) => s.setQuickView);

  return (
    <button
      type="button"
      onClick={() => open(slug)}
      aria-label={t("quickView")}
      className={cn(
        "inline-flex h-9 items-center gap-1.5 rounded-full bg-card/95 px-3.5 text-xs font-semibold shadow-sm backdrop-blur transition-colors hover:text-primary",
        className,
      )}
    >
      <Eye className="size-4" strokeWidth={1.8} />
      {t("quickView")}
    </button>
  );
}
