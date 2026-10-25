import { routing, type Locale } from "@/i18n/routing";

/** Public storefront origin, used for canonical URLs, the sitemap and structured data. */
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");

/** Bangla lives at "/", English under "/en" (`localePrefix: "as-needed"`). */
export function localePath(path: string, locale: string): string {
  const clean = path === "/" ? "" : path;
  return locale === routing.defaultLocale ? clean || "/" : `/${locale}${clean}`;
}

export function absoluteUrl(path: string, locale: string = routing.defaultLocale): string {
  return `${SITE_URL}${localePath(path, locale)}`;
}

/** Canonical + hreflang links for one page that exists in every locale. */
export function alternates(path: string, locale: string) {
  return {
    canonical: localePath(path, locale),
    languages: {
      ...Object.fromEntries(routing.locales.map((l: Locale) => [l, localePath(path, l)])),
      "x-default": localePath(path, routing.defaultLocale),
    },
  };
}
