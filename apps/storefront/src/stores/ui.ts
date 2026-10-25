"use client";

import { create } from "zustand";

type UiState = {
  cartOpen: boolean;
  /** Product slug shown in the quick-view dialog, or null. */
  quickView: string | null;
  setCartOpen: (open: boolean) => void;
  setQuickView: (slug: string | null) => void;
};

export const useUi = create<UiState>()((set) => ({
  cartOpen: false,
  quickView: null,
  // Opening the cart replaces any open quick view, so the two dialogs never stack.
  setCartOpen: (cartOpen) => set(cartOpen ? { cartOpen, quickView: null } : { cartOpen }),
  setQuickView: (quickView) => set({ quickView }),
}));
