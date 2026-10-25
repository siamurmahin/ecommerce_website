import { Search } from "lucide-react";
import { Suspense } from "react";
import { getLocale, getTranslations } from "next-intl/server";
import { getPathname } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { SearchBox, searchInputClass } from "./search-box";

/** The search box reads `?q=` from the URL, so it sits in Suspense; this plain form shows until then. */
export async function SearchForm({ className, autoFocus }: { className?: string; autoFocus?: boolean }) {
  const [t, locale] = [await getTranslations("nav"), await getLocale()];

  const fallback = (
    <form action={getPathname({ href: "/search", locale })} role="search" className={cn("relative", className)}>
      <label htmlFor="site-search" className="sr-only">
        {t("search")}
      </label>
      <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground" strokeWidth={1.8} />
      <input id="site-search" name="q" type="search" placeholder={t("searchPlaceholder")} className={searchInputClass} />
    </form>
  );

  return (
    <Suspense fallback={fallback}>
      <SearchBox className={className} autoFocus={autoFocus} />
    </Suspense>
  );
}
