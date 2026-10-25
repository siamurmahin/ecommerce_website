"use client";

import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { ProductGrid } from "@/components/commerce/product-grid";
import { SectionHeading } from "@/components/home/section-heading";
import { getCrossSells } from "@/lib/api/cart";

/** "এটাও নিতে পারেন": cross-sells set on the products in the cart. */
export function CrossSells({ productIds }: { productIds: number[] }) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const ids = [...new Set(productIds)].sort((a, b) => a - b);

  const { data } = useQuery({
    queryKey: ["cross-sells", locale, ids],
    queryFn: () => getCrossSells(ids, locale),
    enabled: ids.length > 0,
    staleTime: 5 * 60_000,
  });

  if (!data || data.length === 0) return null;

  return (
    <section className="flex flex-col gap-6">
      <SectionHeading title={t("crossSells")} />
      <ProductGrid products={data.slice(0, 4)} />
    </section>
  );
}
