import { cacheLife, cacheTag } from "next/cache";
import { api, apiOrNull } from "./client";
import type { Category, HomeData, Product, ProductCard, ProductPage, ProductSort, QuestionPage, ReviewPage } from "./types";

// Catalog reads are cached and tagged; the backend revalidates tags when the admin saves (Phase 1, later step).

export async function getHome(locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", "home");
  return api<HomeData>("/home", { locale });
}

export async function getCategories(locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("catalog", "categories");
  return (await api<{ data: Category[] }>("/categories", { locale })).data;
}

export async function getCategory(slug: string, locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("catalog", `category:${slug}`);
  return (await apiOrNull<{ data: Category }>(`/categories/${encodeURIComponent(slug)}`, { locale }))?.data ?? null;
}

export type ProductQuery = {
  category?: string;
  q?: string;
  badge?: string;
  sort?: ProductSort;
  page?: number;
  perPage?: number;
};

export async function getProducts(query: ProductQuery, locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", "products");

  const params = new URLSearchParams();
  if (query.category) params.set("category", query.category);
  if (query.q) params.set("q", query.q);
  if (query.badge) params.set("badge", query.badge);
  if (query.sort) params.set("sort", query.sort);
  if (query.page) params.set("page", String(query.page));
  params.set("per_page", String(query.perPage ?? 24));

  return api<ProductPage>(`/products?${params}`, { locale });
}

export async function getProduct(slug: string, locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return (await apiOrNull<{ data: Product }>(`/products/${encodeURIComponent(slug)}`, { locale }))?.data ?? null;
}

/** First page of approved reviews; later pages load in the browser. */
export async function getProductReviews(slug: string, locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return apiOrNull<ReviewPage>(`/products/${encodeURIComponent(slug)}/reviews`, { locale });
}

export async function getProductQuestions(slug: string, locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return apiOrNull<QuestionPage>(`/products/${encodeURIComponent(slug)}/questions`, { locale });
}

export async function getRelated(slug: string, locale: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return (await api<{ data: ProductCard[] }>(`/products/${encodeURIComponent(slug)}/related`, { locale })).data;
}
