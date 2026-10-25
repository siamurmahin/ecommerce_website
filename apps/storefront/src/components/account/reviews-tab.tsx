"use client";

import { useQuery } from "@tanstack/react-query";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { ReviewDialog } from "@/components/reviews/review-dialog";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { getReviewable } from "@/lib/api/customer";
import type { ProductCard } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export function ReviewsTab() {
  const t = useTranslations("account");
  const tr = useTranslations("reviews");
  const locale = useLocale();
  const { data: products, isPending } = useQuery({ queryKey: ["reviewable", locale], queryFn: () => getReviewable(locale) });
  const [target, setTarget] = useState<ProductCard | null>(null);

  if (isPending) return <div className="h-48 animate-pulse rounded-lg bg-card" aria-hidden />;

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t("reviewsNote")}</p>
      {!products || products.length === 0 ? (
        <p className="rounded-lg bg-card px-6 py-12 text-center text-muted-foreground">{t("noReviewable")}</p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {products.map((p) => (
            <li key={p.id} className="flex items-center gap-4 rounded-lg bg-card p-4">
              <Link href={`/p/${p.slug}`} className="relative size-16 shrink-0 overflow-hidden rounded-md bg-placeholder">
                {p.image && <Image src={p.image} alt="" fill sizes="64px" className="object-cover" />}
              </Link>
              <span className="flex min-w-0 flex-1 flex-col gap-2">
                <span className="truncate text-sm font-semibold">{p.name}</span>
                <button type="button" onClick={() => setTarget(p)} className={cn(buttonVariants({ size: "sm" }), "self-start rounded-full px-4")}>
                  {tr("write")}
                </button>
              </span>
            </li>
          ))}
        </ul>
      )}
      <ReviewDialog product={target} open={target !== null} onOpenChange={(open) => !open && setTarget(null)} />
    </div>
  );
}
