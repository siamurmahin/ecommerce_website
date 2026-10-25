"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { Check, Copy, Gift, Loader2, Share2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import { getPoints } from "@/lib/api/customer";
import type { Customer } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

/** Points balance and history, plus the customer's invite code. */
export function RewardsTab({ customer }: { customer: Customer }) {
  const t = useTranslations("account.rewards");
  const locale = useLocale();
  const { points, referral } = customer.rewards;
  const [copied, setCopied] = useState(false);
  const history = useInfiniteQuery({
    queryKey: ["points", locale],
    queryFn: ({ pageParam }) => getPoints(pageParam, locale),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    enabled: !!points,
  });
  const rows = history.data?.pages.flatMap((p) => p.data) ?? [];

  async function share() {
    if (!referral) return;
    const text = t("shareText", { amount: formatTaka(referral.friend_discount, locale), code: referral.code });
    try {
      if (navigator.share) await navigator.share({ text, url: referral.link });
      else {
        await navigator.clipboard.writeText(`${text} ${referral.link}`);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      }
    } catch {
      // Share sheet dismissed.
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {points && (
        <section className="flex flex-col gap-1 rounded-lg bg-card p-6">
          <span className="text-sm text-muted-foreground">{t("balance")}</span>
          <strong className="font-heading text-4xl font-semibold tabular-nums">{formatNumber(points.balance, locale)}</strong>
          <span className="text-sm text-muted-foreground">
            {t("worth", { amount: formatTaka(points.balance * points.point_value, locale) })} · {t("earnRule", { points: formatNumber(points.earn_per_100, locale) })}
          </span>
        </section>
      )}

      {referral && (
        <section className="flex flex-col gap-3 rounded-lg bg-card p-6">
          <h2 className="flex items-center gap-2 font-semibold">
            <Gift className="size-5 text-primary" strokeWidth={1.8} />
            {t("referTitle")}
          </h2>
          <p className="text-sm text-muted-foreground">
            {t("referNote", {
              amount: formatTaka(referral.friend_discount, locale),
              min: formatTaka(referral.friend_min_subtotal, locale),
              points: formatNumber(referral.your_points, locale),
            })}
          </p>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-md border border-dashed border-primary/40 bg-secondary px-4 py-2 font-mono text-lg font-semibold tracking-widest">{referral.code}</span>
            <button type="button" onClick={share} className={cn(buttonVariants(), "h-11 rounded-full px-5")}>
              {copied ? <Check className="size-4" /> : typeof navigator !== "undefined" && "share" in navigator ? <Share2 className="size-4" /> : <Copy className="size-4" />}
              {copied ? t("copied") : t("share")}
            </button>
          </div>
        </section>
      )}

      {!points && !referral && <p className="rounded-lg bg-card px-6 py-12 text-center text-muted-foreground">{t("off")}</p>}

      {points && (
        <section className="flex flex-col gap-2 rounded-lg bg-card p-6">
          <h2 className="font-semibold">{t("history")}</h2>
          {history.isPending ? (
            <div className="h-24 animate-pulse rounded-md bg-secondary" aria-hidden />
          ) : rows.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t("noHistory")}</p>
          ) : (
            <ul className="flex flex-col divide-y divide-border">
              {rows.map((r) => (
                <li key={r.id} className="flex items-center justify-between gap-4 py-3 text-sm">
                  <span className="flex flex-col">
                    <span className="font-medium">{t(`types.${r.type}`)}</span>
                    <span className="text-xs text-muted-foreground">
                      {[r.order_number, r.note, new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" }).format(new Date(r.created_at))]
                        .filter(Boolean)
                        .join(" · ")}
                    </span>
                  </span>
                  <span className={cn("font-semibold tabular-nums", r.points > 0 ? "text-primary" : "text-destructive")}>
                    {r.points > 0 ? "+" : "−"}
                    {formatNumber(Math.abs(r.points), locale)}
                  </span>
                </li>
              ))}
            </ul>
          )}
          {history.hasNextPage && (
            <button type="button" onClick={() => history.fetchNextPage()} disabled={history.isFetchingNextPage} className={cn(buttonVariants({ variant: "outline" }), "h-10 self-center rounded-full px-5")}>
              {history.isFetchingNextPage && <Loader2 className="size-4 animate-spin" />}
              {t("loadMore")}
            </button>
          )}
        </section>
      )}
    </div>
  );
}
