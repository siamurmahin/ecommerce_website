import { api, API_URL } from "./client";
import type { CartItemInput, ProductCard, PlacedOrder, PlaceOrderInput, Quote, SaveDraftInput, TrackedOrder, TrackOrderInput } from "./types";

// Browser-side calls. Never cached: prices and stock must be live.

/** Prices the cart from the database. */
export async function quoteCart(items: CartItemInput[], districtId: number | null, locale: string, couponCode: string | null = null) {
  return (await api<{ data: Quote }>("/cart/quote", { locale, json: { items, district_id: districtId, coupon_code: couponCode } })).data;
}

/** Add-ons for the products in the cart. */
export async function getCrossSells(productIds: number[], locale: string) {
  return (await api<{ data: ProductCard[] }>(`/products/cross-sells?ids=${productIds.join(",")}`, { locale })).data;
}

export async function placeOrder(input: PlaceOrderInput, locale: string) {
  return api<PlacedOrder>("/orders", { locale, json: input });
}

/** Number + phone → access token for the order page. 404 when they don't match. */
export async function trackOrder(input: TrackOrderInput, locale: string) {
  return api<TrackedOrder>("/orders/track", { locale, json: input });
}

/** Saves the half-filled checkout as an incomplete order (204, fire-and-forget). */
export function saveDraft(input: SaveDraftInput, locale: string) {
  return fetch(`${API_URL}/checkout/draft?locale=${locale}`, {
    method: "POST",
    keepalive: true,
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(input),
  }).catch(() => undefined);
}
