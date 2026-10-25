"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { CartItemInput, QuoteLine } from "@/lib/api/types";

export type CartLine = {
  key: string; // productId or productId:variantId
  productId: number;
  variantId: number | null;
  slug: string;
  name: string;
  variantLabel: string | null;
  image: string | null;
  price: number; // poisha
  quantity: number;
  maxQuantity: number;
};

type CartState = {
  lines: CartLine[];
  /** Coupon the customer entered; re-validated by every quote. */
  coupon: string | null;
  setCoupon: (code: string | null) => void;
  add: (line: Omit<CartLine, "key" | "quantity">, quantity?: number) => void;
  setQuantity: (key: string, quantity: number) => void;
  remove: (key: string) => void;
  clear: () => void;
  /** Apply server prices/stock from a quote so the stored snapshot stays honest. */
  syncWithQuote: (lines: QuoteLine[]) => void;
};

/** Guest cart kept in localStorage. Prices are re-checked by the API at checkout. */
export const useCart = create<CartState>()(
  persist(
    (set) => ({
      lines: [],
      coupon: null,
      setCoupon: (coupon) => set({ coupon }),
      add: (line, quantity = 1) =>
        set((state) => {
          const key = line.variantId ? `${line.productId}:${line.variantId}` : String(line.productId);
          const existing = state.lines.find((l) => l.key === key);
          if (existing) {
            return {
              lines: state.lines.map((l) =>
                l.key === key ? { ...l, quantity: Math.min(l.quantity + quantity, line.maxQuantity) } : l,
              ),
            };
          }
          return { lines: [...state.lines, { ...line, key, quantity: Math.min(quantity, line.maxQuantity) }] };
        }),
      setQuantity: (key, quantity) =>
        set((state) => ({
          lines: state.lines.map((l) => (l.key === key ? { ...l, quantity: Math.max(1, Math.min(quantity, l.maxQuantity)) } : l)),
        })),
      remove: (key) => set((state) => ({ lines: state.lines.filter((l) => l.key !== key) })),
      clear: () => set({ lines: [], coupon: null }),
      syncWithQuote: (quoted) =>
        set((state) => {
          let changed = false;
          const lines = state.lines.map((line) => {
            const q = quoted.find((ql) => ql.product_id === line.productId && (ql.variant_id ?? null) === line.variantId);
            if (!q || q.problem === "unavailable") return line;
            const maxQuantity = Math.max(1, q.available);
            if (q.unit_price === line.price && maxQuantity === line.maxQuantity) return line;
            changed = true;
            return { ...line, price: q.unit_price, maxQuantity, quantity: Math.min(line.quantity, maxQuantity) };
          });
          return changed ? { lines } : state;
        }),
    }),
    { name: "lj-cart" },
  ),
);

export const cartCount = (lines: CartLine[]) => lines.reduce((sum, l) => sum + l.quantity, 0);

export const cartSubtotal = (lines: CartLine[]) => lines.reduce((sum, l) => sum + l.price * l.quantity, 0);

export const toCartItems = (lines: CartLine[]): CartItemInput[] =>
  lines.map((l) => ({ product_id: l.productId, variant_id: l.variantId, quantity: l.quantity }));
