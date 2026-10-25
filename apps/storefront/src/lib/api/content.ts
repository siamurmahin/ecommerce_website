import { cacheLife, cacheTag } from "next/cache";
import { api, apiOrNull } from "./client";
import type { LandingList, LandingPage, Menus, Page, PageList, SiteSettings, SitemapData, TrackingConfig } from "./types";

export async function getSettings(locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "settings");
  return api<SiteSettings>("/settings", { locale });
}

export async function getMenus(locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "menus");
  return api<Menus>("/menus", { locale });
}

export async function getPage(slug: string, locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("content", `page:${slug}`);
  return (await apiOrNull<{ data: Page }>(`/pages/${encodeURIComponent(slug)}`, { locale }))?.data ?? null;
}

export async function getPageSlugs() {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "pages");
  return (await api<PageList>("/pages")).data;
}

/** Ad landing page with its product; "catalog" keeps price and stock in step with product edits. */
export async function getLandingPage(slug: string, locale: string) {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "catalog", `landing:${slug}`);
  return (await apiOrNull<{ data: LandingPage }>(`/landing-pages/${encodeURIComponent(slug)}`, { locale }))?.data ?? null;
}

export async function getLandingSlugs() {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "landings");
  return (await api<LandingList>("/landing-pages")).data;
}

export async function getTracking() {
  "use cache";
  cacheLife("hours");
  cacheTag("content", "tracking");
  return api<TrackingConfig>("/tracking");
}

export async function getSitemap() {
  "use cache";
  cacheLife("hours");
  cacheTag("catalog", "pages");
  return api<SitemapData>("/sitemap");
}
