"use client";

import { Heart } from "lucide-react";
import { motion } from "motion/react";
import { useTranslations } from "next-intl";
import { useHydrated } from "@/hooks/use-hydrated";
import type { ProductCard } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useWishlist } from "@/stores/wishlist";

export function WishlistButton({ product, className }: { product: ProductCard; className?: string }) {
  const t = useTranslations("card");
  const hydrated = useHydrated();
  const saved = useWishlist((s) => s.items.some((p) => p.id === product.id));
  const toggle = useWishlist((s) => s.toggle);
  const active = hydrated && saved;

  return (
    <motion.button
      type="button"
      whileTap={{ scale: 0.85 }}
      onClick={() => toggle(product)}
      aria-pressed={active}
      aria-label={active ? t("wishlistRemove") : t("wishlistAdd")}
      className={cn(
        "grid size-9 place-items-center rounded-full bg-card/90 shadow-sm backdrop-blur transition-colors hover:text-primary",
        active ? "text-[#C2410C]" : "text-foreground",
        className,
      )}
    >
      <motion.span key={String(active)} initial={active ? { scale: 0.5 } : false} animate={{ scale: 1 }} transition={{ type: "spring", stiffness: 500, damping: 15 }}>
        <Heart className={cn("size-[18px]", active && "fill-current")} strokeWidth={1.8} />
      </motion.span>
    </motion.button>
  );
}
