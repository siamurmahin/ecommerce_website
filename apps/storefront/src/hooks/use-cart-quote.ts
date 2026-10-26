"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { useEffect } from "react";
import { type LandingQuote, quoteCart } from "@/lib/api/cart";
import type { CartItemInput } from "@/lib/api/types";
import { type CartLine, toCartItems, useCart } from "@/stores/cart";

/**
 * Server-priced view of the current cart (with the stored coupon); also writes fresh prices/stock
 * back into the stored cart. `extra` adds lines that aren't in the cart store, e.g. checkout bumps.
 * `override` prices other lines instead (a landing page's own order) and leaves the cart alone;
 * `landing` adds that page's chosen package.
 */
export function useCartQuote(districtId: number | null = null, extra: CartItemInput[] = [], override?: CartLine[], landing: LandingQuote | null = null) {
  const locale = useLocale();
  const stored = useCart((s) => s.lines);
  const storedCoupon = useCart((s) => s.coupon);
  const syncWithQuote = useCart((s) => s.syncWithQuote);
  const ownCart = override === undefined;
  const lines = override ?? stored;
  const coupon = ownCart ? storedCoupon : null;
  const items = [...toCartItems(lines), ...extra];

  const query = useQuery({
    queryKey: ["cart-quote", locale, districtId, coupon, items, landing],
    queryFn: () => quoteCart(items, districtId, locale, coupon, landing),
    enabled: items.length > 0,
    placeholderData: keepPreviousData,
  });

  useEffect(() => {
    if (query.data && ownCart) syncWithQuote(query.data.lines.filter((l) => !l.bump_id));
  }, [query.data, syncWithQuote, ownCart]);

  return query;
}
