"use client";

import { Heart } from "lucide-react";
import { useTranslations } from "next-intl";
import { ProductGrid, ProductGridSkeleton } from "@/components/commerce/product-grid";
import { buttonVariants } from "@/components/ui/button";
import { useHydrated } from "@/hooks/use-hydrated";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { useWishlist } from "@/stores/wishlist";

export function WishlistView() {
  const t = useTranslations("wishlist");
  const tc = useTranslations("cart");
  const hydrated = useHydrated();
  const items = useWishlist((s) => s.items);

  if (!hydrated) return <ProductGridSkeleton count={4} />;

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4 rounded-lg bg-card px-6 py-16 text-center">
        <span className="grid size-16 place-items-center rounded-full bg-secondary text-primary">
          <Heart className="size-7" strokeWidth={1.5} />
        </span>
        <div className="flex flex-col gap-1">
          <p className="font-heading text-xl font-semibold">{t("empty")}</p>
          <p className="text-sm text-muted-foreground">{t("emptyNote")}</p>
        </div>
        <Link href="/shop" className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {tc("startShopping")}
        </Link>
      </div>
    );
  }

  return <ProductGrid products={items} />;
}
