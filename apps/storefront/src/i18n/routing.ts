import { defineRouting } from "next-intl/routing";

// Bangla lives at "/", English at "/en". Detection is off so every visitor starts in Bangla.
export const routing = defineRouting({
  locales: ["bn", "en"],
  defaultLocale: "bn",
  localePrefix: "as-needed",
  localeDetection: false,
});

export type Locale = (typeof routing.locales)[number];
