"use client";

import { ShoppingBag } from "lucide-react";
import { AnimatePresence, motion } from "motion/react";
import { useTranslations } from "next-intl";
import { useHydrated } from "@/hooks/use-hydrated";
import { cartCount, useCart } from "@/stores/cart";
import { useUi } from "@/stores/ui";

/** Opens the cart drawer; the count badge shows after hydration to match the persisted cart. */
export function CartButton({ className }: { className?: string }) {
  const t = useTranslations("nav");
  const count = useCart((s) => cartCount(s.lines));
  const openCart = useUi((s) => s.setCartOpen);
  const mounted = useHydrated();

  return (
    <button type="button" onClick={() => openCart(true)} aria-label={t("cart")} className={className}>
      <span className="relative inline-flex">
        <ShoppingBag className="size-[22px]" strokeWidth={1.7} />
        <AnimatePresence>
          {mounted && count > 0 && (
            <motion.span
              key={count}
              initial={{ scale: 0.4, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.4, opacity: 0 }}
              transition={{ type: "spring", stiffness: 500, damping: 24 }}
              className="absolute -top-1.5 -right-2 min-w-4 rounded-full bg-gold px-1 text-center text-[10px] leading-4 font-bold text-[#171717] tabular-nums"
            >
              {count}
            </motion.span>
          )}
        </AnimatePresence>
      </span>
    </button>
  );
}
