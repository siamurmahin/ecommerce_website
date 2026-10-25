"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { ProductCard } from "@/lib/api/types";

type WishlistState = {
  items: ProductCard[];
  toggle: (product: ProductCard) => void;
  setItems: (items: ProductCard[]) => void;
};

/** Wishlist in localStorage; mirrored to the customer's account while signed in (see WishlistSync). */
export const useWishlist = create<WishlistState>()(
  persist(
    (set) => ({
      items: [],
      toggle: (product) =>
        set((state) =>
          state.items.some((p) => p.id === product.id)
            ? { items: state.items.filter((p) => p.id !== product.id) }
            : { items: [product, ...state.items] },
        ),
      setItems: (items) => set({ items }),
    }),
    { name: "lj-wishlist" },
  ),
);
