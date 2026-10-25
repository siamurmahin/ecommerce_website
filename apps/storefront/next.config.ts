import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const assetOrigin = new URL(process.env.NEXT_PUBLIC_ASSET_ORIGIN ?? "http://localhost:8000");
const localAssets = ["localhost", "127.0.0.1", "[::1]"].includes(assetOrigin.hostname);

const nextConfig: NextConfig = {
  cacheComponents: true,
  // Lets the dev server be opened as 127.0.0.1 when another tool holds localhost:3000 on IPv6.
  allowedDevOrigins: ["127.0.0.1"],
  partialPrefetching: true,
  images: {
    remotePatterns: [new URL(`${assetOrigin.origin}/storage/**`)],
    // Local dev, local production builds and CI serve product images from the Laravel app on localhost.
    dangerouslyAllowLocalIP: process.env.NODE_ENV !== "production" || localAssets,
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default withNextIntl(nextConfig);
