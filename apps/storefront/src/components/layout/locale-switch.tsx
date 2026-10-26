"use client";

import { Languages } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

/** Shown only when both languages are on (সেটিংস → ভাষা). */
export function LocaleSwitch({ enabled = true }: { enabled?: boolean }) {
  const t = useTranslations("nav");
  const locale = useLocale();
  const router = useRouter();

  // Read the URL on click rather than with usePathname(), so this button stays in the static shell
  // on dynamic routes and keeps the query string (search terms, order tokens).
  const switchLocale = () => {
    const { pathname, search } = window.location;
    const prefix = `/${locale}`;
    const path = locale !== routing.defaultLocale && pathname.startsWith(prefix) ? pathname.slice(prefix.length) || "/" : pathname;
    const next = locale === "bn" ? "en" : "bn";
    // Remember the choice so the proxy stops sending this visitor to the shop's main language.
    document.cookie = `lj_lang=${next}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    router.replace(`${path}${search}`, { locale: next });
  };

  if (!enabled) return null;

  return (
    <button
      type="button"
      onClick={switchLocale}
      className="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
    >
      <Languages className="size-4" strokeWidth={1.7} />
      {t("language")}
    </button>
  );
}
