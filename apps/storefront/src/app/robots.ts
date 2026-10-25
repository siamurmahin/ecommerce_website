import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site";

// Personal or thin pages stay out of the index, in both locales.
const PRIVATE = ["/cart", "/checkout", "/order/", "/wishlist", "/track-order", "/search"];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: "*", allow: "/", disallow: ["/api/", ...PRIVATE, ...PRIVATE.map((p) => `/en${p}`)] },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
