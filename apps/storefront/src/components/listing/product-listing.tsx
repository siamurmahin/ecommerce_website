import { getTranslations } from "next-intl/server";
import { ProductGrid } from "@/components/commerce/product-grid";
import { Link } from "@/i18n/navigation";
import { getProducts, type ProductQuery } from "@/lib/api/catalog";
import type { ProductSort } from "@/lib/api/types";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";
import { SortSelect } from "./sort-select";

const SORTS: ProductSort[] = ["newest", "price_asc", "price_desc", "popular"];

type Props = {
  base: Omit<ProductQuery, "sort" | "page">;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
  pathname: string;
  locale: string;
};

/** Reads sort/page/q from the URL, so callers render it inside <Suspense>. */
export async function ProductListing({ base, searchParams, pathname, locale }: Props) {
  const params = await searchParams;
  const str = (k: string) => (typeof params[k] === "string" ? (params[k] as string) : undefined);
  const sort = SORTS.find((s) => s === str("sort"));
  const page = Math.max(1, Number(str("page")) || 1);

  const [t, result] = await Promise.all([
    getTranslations("listing"),
    getProducts({ ...base, q: base.q ?? str("q"), badge: base.badge ?? str("badge"), sort, page }, locale),
  ]);
  const { data, meta } = result;

  const pageHref = (n: number) => {
    const next = new URLSearchParams(Object.entries(params).filter((e): e is [string, string] => typeof e[1] === "string"));
    next.set("page", String(n));
    return `${pathname}?${next}`;
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
        <p className="text-sm text-muted-foreground tabular-nums">{t("products", { count: formatNumber(meta.total ?? 0, locale) })}</p>
        <SortSelect />
      </div>

      {result.search?.corrected_to && (
        <p className="rounded-md bg-secondary px-4 py-3 text-sm">
          {t("correctedTo", { term: str("q") ?? "", corrected: result.search.corrected_to })}
        </p>
      )}

      {data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-border py-20 text-center text-muted-foreground">{t("empty")}</p>
      ) : (
        <ProductGrid products={data} priorityCount={4} />
      )}

      {meta.last_page > 1 && (
        <nav aria-label="pagination" className="flex justify-center gap-2 pt-6">
          {Array.from({ length: meta.last_page }, (_, i) => i + 1).map((n) => (
            <Link
              key={n}
              href={pageHref(n)}
              aria-current={n === meta.current_page ? "page" : undefined}
              className={cn(
                "grid size-10 place-items-center rounded-full border text-sm tabular-nums transition-colors",
                n === meta.current_page ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card hover:border-primary",
              )}
            >
              {formatNumber(n, locale)}
            </Link>
          ))}
        </nav>
      )}
    </div>
  );
}
