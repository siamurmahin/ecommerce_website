"use client";

import { useLocale } from "next-intl";
import { useEffect } from "react";
import { useCustomer } from "@/hooks/use-customer";
import { setWishlisted, syncWishlist } from "@/lib/api/customer";
import { useWishlist } from "@/stores/wishlist";

/**
 * Keeps the local wishlist and the signed-in customer's saved list in step: on sign-in the device's
 * items merge into the account (and the account's come back), then each heart tap is mirrored.
 */
export function WishlistSync() {
  const locale = useLocale();
  const { data: customer } = useCustomer();
  const customerId = customer?.id;

  useEffect(() => {
    if (!customerId) return;
    let merging = true;
    let cancelled = false;

    syncWishlist(
      useWishlist.getState().items.map((p) => p.id),
      locale,
    )
      .then((items) => !cancelled && useWishlist.getState().setItems(items))
      .catch(() => {})
      .finally(() => (merging = false));

    const unsubscribe = useWishlist.subscribe((state, prev) => {
      if (merging) return;
      const before = new Set(prev.items.map((p) => p.id));
      const after = new Set(state.items.map((p) => p.id));
      after.forEach((id) => !before.has(id) && setWishlisted(id, true, locale).catch(() => {}));
      before.forEach((id) => !after.has(id) && setWishlisted(id, false, locale).catch(() => {}));
    });

    return () => {
      cancelled = true;
      unsubscribe();
    };
  }, [customerId, locale]);

  return null;
}
