import type { MetadataRoute } from "next";
import { routing } from "@/i18n/routing";
import { getLandingSlugs, getSitemap } from "@/lib/api/content";
import { absoluteUrl } from "@/lib/site";

type Entry = MetadataRoute.Sitemap[number];

/** One entry per URL in each locale, each listing its hreflang siblings. */
function entry(path: string, extra: Omit<Entry, "url" | "alternates"> = {}): Entry[] {
  const languages = Object.fromEntries(routing.locales.map((l) => [l, absoluteUrl(path, l)]));
  return routing.locales.map((locale) => ({ url: absoluteUrl(path, locale), alternates: { languages }, ...extra }));
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [{ products, categories, pages }, landings] = await Promise.all([getSitemap(), getLandingSlugs()]);

  return [
    ...entry("/", { changeFrequency: "daily", priority: 1 }),
    ...entry("/shop", { changeFrequency: "daily", priority: 0.8 }),
    ...entry("/categories", { changeFrequency: "weekly", priority: 0.6 }),
    ...categories.flatMap((c) => entry(`/c/${c.slug}`, { lastModified: c.updated_at, changeFrequency: "daily", priority: 0.7 })),
    ...products.flatMap((p) => entry(`/p/${p.slug}`, { lastModified: p.updated_at, changeFrequency: "weekly", priority: 0.9, images: p.images })),
    ...pages.flatMap((p) => entry(`/${p.slug}`, { lastModified: p.updated_at, changeFrequency: "monthly", priority: 0.3 })),
    ...landings.filter((l) => l.indexable).flatMap((l) => entry(`/l/${l.slug}`, { lastModified: l.updated_at, changeFrequency: "weekly", priority: 0.5 })),
  ];
}
