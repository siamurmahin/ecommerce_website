"use client";

import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import type { ProductSort } from "@/lib/api/types";

const options: { value: ProductSort; key: string }[] = [
  { value: "newest", key: "sortNewest" },
  { value: "popular", key: "sortPopular" },
  { value: "price_asc", key: "sortPriceAsc" },
  { value: "price_desc", key: "sortPriceDesc" },
];

export function SortSelect() {
  const t = useTranslations("listing");
  const params = useSearchParams();
  const pathname = usePathname();
  const router = useRouter();

  return (
    <label className="inline-flex items-center gap-2 text-sm">
      <span className="text-muted-foreground">{t("sort")}</span>
      <select
        id="sort"
        value={params.get("sort") ?? "newest"}
        onChange={(e) => {
          const next = new URLSearchParams(params);
          next.set("sort", e.target.value);
          next.delete("page");
          router.replace(`${pathname}?${next}`, { scroll: false });
        }}
        className="h-10 rounded-full border border-border bg-card px-4 text-sm outline-none focus:border-primary"
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {t(o.key)}
          </option>
        ))}
      </select>
    </label>
  );
}
