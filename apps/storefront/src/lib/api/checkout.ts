import { cacheLife, cacheTag } from "next/cache";
import { api, apiOrNull } from "./client";
import type { CheckoutOptions, Order } from "./types";

/** Districts, zones and payment methods. Cached; the backend revalidates "shipping" when zones change. */
export async function getCheckoutOptions(locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("shipping");
  return api<CheckoutOptions>("/checkout/options", { locale });
}

/** Thank-you page; per-customer, so never cached. */
export async function getOrder(number: string, token: string, locale: string) {
  const params = new URLSearchParams({ token });
  return (await apiOrNull<{ data: Order }>(`/orders/${encodeURIComponent(number)}?${params}`, { locale, cache: "no-store" }))?.data ?? null;
}
