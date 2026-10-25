import { api, ApiError } from "./client";
import type { Customer, CustomerAddress, CustomerAddressInput, CustomerOrderPage, OtpSent, PlacedOrder, PlaceOrderInput, PointPage, ProductCard, QuestionPage, RecoveredCart, Review, ReviewPage } from "./types";

// Signed-in customer calls, through the storefront's own /api/customer proxy (it holds the login cookie).

type Init = { method?: string; json?: unknown; form?: FormData; locale: string };

async function customerApi<T>(path: string, { method, json, form, locale }: Init): Promise<T> {
  const res = await fetch(`/api/customer/${path}${path.includes("?") ? "&" : "?"}locale=${locale}`, {
    method: method ?? (json === undefined && form === undefined ? "GET" : "POST"),
    body: form ?? (json === undefined ? undefined : JSON.stringify(json)),
    headers: { Accept: "application/json", ...(json === undefined ? {} : { "Content-Type": "application/json" }) },
  });

  if (!res.ok) throw new ApiError(res.status, `Customer API ${res.status} on ${path}`, await res.json().catch(() => null));
  return (res.status === 204 ? null : res.json()) as Promise<T>;
}

/** The signed-in customer, or null when signed out. */
export async function getMe(locale: string): Promise<Customer | null> {
  try {
    return (await customerApi<{ data: Customer | null }>("me", { locale })).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null;
    throw error;
  }
}

export const sendOtp = (phone: string, locale: string) => customerApi<OtpSent>("otp", { json: { phone }, locale });

export const verifyOtp = (input: { phone: string; code: string; name?: string | null }, locale: string) =>
  customerApi<{ customer: Customer }>("verify", { json: input, locale });

export const logout = (locale: string) => customerApi<null>("logout", { method: "POST", locale });

export const updateProfile = async (input: { name: string; email: string | null }, locale: string) =>
  (await customerApi<{ data: Customer }>("me", { method: "PUT", json: input, locale })).data;

export const getOrders = (page: number, locale: string) => customerApi<CustomerOrderPage>(`orders?page=${page}`, { locale });

export const saveAddress = async (input: CustomerAddressInput, id: number | null, locale: string) =>
  (await customerApi<{ data: CustomerAddress }>(id ? `addresses/${id}` : "addresses", { method: id ? "PUT" : "POST", json: input, locale })).data;

export const deleteAddress = (id: number, locale: string) => customerApi<null>(`addresses/${id}`, { method: "DELETE", locale });

export const getReviewable = async (locale: string) => (await customerApi<{ data: ProductCard[] }>("reviewable", { locale })).data;

export const submitReview = (form: FormData, locale: string) => customerApi<{ data: Review; message: string }>("reviews", { form, locale });

/** Public: approved reviews of a product, straight from the API. */
export const getReviewPage = (slug: string, page: number, locale: string) =>
  api<ReviewPage>(`/products/${encodeURIComponent(slug)}/reviews?page=${page}`, { locale });

/** Public: answered questions on a product. */
export const getQuestionPage = (slug: string, page: number, locale: string) =>
  api<QuestionPage>(`/products/${encodeURIComponent(slug)}/questions?page=${page}`, { locale });

export const askQuestion = (slug: string, input: { name: string; phone: string | null; question: string; website?: string }, locale: string) =>
  api<{ message: string }>(`/products/${encodeURIComponent(slug)}/questions`, { json: input, locale });

export const syncWishlist = async (productIds: number[], locale: string) =>
  (await customerApi<{ data: ProductCard[] }>("wishlist/sync", { json: { product_ids: productIds }, locale })).data;

export const setWishlisted = (productId: number, saved: boolean, locale: string) =>
  customerApi<{ data: ProductCard[] }>(`wishlist/${productId}`, { method: saved ? "PUT" : "DELETE", locale });

/** Public: the saved cart behind a reminder-SMS link. */
export const recoverCart = (token: string, locale: string) => api<RecoveredCart>(`/checkout/recover/${encodeURIComponent(token)}`, { locale });

/** Signed-in checkout (needed to spend points); same response as the guest order. */
export const placeCustomerOrder = (input: PlaceOrderInput, locale: string) => customerApi<PlacedOrder>("orders", { json: input, locale });

export const getPoints = (page: number, locale: string) => customerApi<PointPage>(`points?page=${page}`, { locale });
