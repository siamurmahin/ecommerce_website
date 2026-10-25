"use client";

import { Languages } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

export function LocaleSwitch() {
  const t = useTranslations("nav");
  const locale = useLocale();
  const router = useRouter();

  // Read the URL on click rather than with usePathname(), so this button stays in the static shell
  // on dynamic routes and keeps the query string (search terms, order tokens).
  const switchLocale = () => {
    const { pathname, search } = window.location;
    const prefix = `/${locale}`;
    const path = locale !== routing.defaultLocale && pathname.startsWith(prefix) ? pathname.slice(prefix.length) || "/" : pathname;
    router.replace(`${path}${search}`, { locale: locale === "bn" ? "en" : "bn" });
  };

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
