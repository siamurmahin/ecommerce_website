import type { MetadataRoute } from "next";
import { getSettings } from "@/lib/api/content";
import { routing } from "@/i18n/routing";

/** "Add to home screen" — name and colours follow admin → ব্র্যান্ড ও থিম. */
export default async function manifest(): Promise<MetadataRoute.Manifest> {
  const { brand, theme } = await getSettings(routing.defaultLocale);

  return {
    name: brand.tagline ? `${brand.name} — ${brand.tagline}` : brand.name,
    short_name: brand.name,
    description: brand.tagline ?? undefined,
    start_url: "/",
    scope: "/",
    display: "standalone",
    lang: routing.defaultLocale,
    background_color: theme.colors.background,
    theme_color: theme.colors.background,
    icons: [
      { src: "/pwa-icons/192.png", sizes: "192x192", type: "image/png", purpose: "any" },
      { src: "/pwa-icons/512.png", sizes: "512x512", type: "image/png", purpose: "any" },
      { src: "/pwa-icons/maskable-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
  };
}
