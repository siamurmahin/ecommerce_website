"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { BadgeCheck, Loader2, Store } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { getReviewPage } from "@/lib/api/customer";
import type { Review, ReviewPage } from "@/lib/api/types";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Stars } from "./stars";

/** Server-rendered first page, further pages fetched on demand. */
export function ReviewList({ slug, initial }: { slug: string; initial: ReviewPage }) {
  const t = useTranslations("reviews");
  const locale = useLocale();
  const { data, fetchNextPage, hasNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ["reviews", slug, locale],
    queryFn: ({ pageParam }) => getReviewPage(slug, pageParam, locale),
    initialPageParam: 1,
    initialData: { pages: [initial], pageParams: [1] },
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    staleTime: 60_000,
  });
  const reviews = data.pages.flatMap((p) => p.data);

  return (
    <div className="flex flex-col gap-4">
      <ul className="flex flex-col gap-3">
        {reviews.map((r) => (
          <ReviewItem key={r.id} review={r} />
        ))}
      </ul>
      {hasNextPage && (
        <button type="button" onClick={() => fetchNextPage()} disabled={isFetchingNextPage} className={cn(buttonVariants({ variant: "outline" }), "h-11 self-center rounded-full px-6")}>
          {isFetchingNextPage && <Loader2 className="size-4 animate-spin" />}
          {t("loadMore")}
        </button>
      )}
    </div>
  );
}

function ReviewItem({ review }: { review: Review }) {
  const t = useTranslations("reviews");
  const locale = useLocale();
  const date = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" }).format(new Date(review.created_at));
  return (
    <li className="flex flex-col gap-3 rounded-lg bg-card p-5">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <Stars value={review.rating} className="text-sm" label={t("ratingAria", { rating: formatNumber(review.rating, locale) })} />
        <span className="font-semibold">{review.name}</span>
        {review.verified && (
          <span className="inline-flex items-center gap-1 text-xs font-semibold text-primary">
            <BadgeCheck className="size-3.5" strokeWidth={2} />
            {t("verified")}
          </span>
        )}
        <time dateTime={review.created_at} className="ml-auto text-xs text-muted-foreground">
          {date}
        </time>
      </div>
      {review.body && <p className="leading-relaxed whitespace-pre-line text-foreground/85">{review.body}</p>}
      {review.images.length > 0 && (
        <div className="flex gap-2">
          {review.images.map((src) => (
            <a key={src} href={src} target="_blank" rel="noreferrer" className="relative size-20 overflow-hidden rounded-md bg-placeholder">
              <Image src={src} alt="" fill sizes="80px" className="object-cover" />
            </a>
          ))}
        </div>
      )}
      {review.reply && (
        <div className="flex gap-3 rounded-md bg-secondary px-4 py-3 text-sm">
          <Store className="mt-0.5 size-4 shrink-0 text-primary" strokeWidth={1.8} />
          <p>
            <span className="font-semibold">{t("reply")}: </span>
            {review.reply}
          </p>
        </div>
      )}
    </li>
  );
}
