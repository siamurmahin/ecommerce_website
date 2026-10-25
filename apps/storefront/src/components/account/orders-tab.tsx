"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { ChevronRight, Loader2 } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { getOrders } from "@/lib/api/customer";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

export function OrdersTab() {
  const t = useTranslations("account");
  const locale = useLocale();
  const query = useInfiniteQuery({
    queryKey: ["account-orders", locale],
    queryFn: ({ pageParam }) => getOrders(pageParam, locale),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });

  if (query.isPending) return <div className="h-64 animate-pulse rounded-lg bg-card" aria-hidden />;
  const orders = query.data?.pages.flatMap((p) => p.data) ?? [];

  if (orders.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4 rounded-lg bg-card px-6 py-14 text-center">
        <p className="text-muted-foreground">{t("noOrders")}</p>
        <Link href="/shop" className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {t("startShopping")}
        </Link>
      </div>
    );
  }

  const date = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" });

  return (
    <div className="flex flex-col gap-3">
      <ul className="flex flex-col gap-3">
        {orders.map((order) => (
          <li key={order.number}>
            <Link
              href={`/order/${order.number}?token=${order.access_token}`}
              className="flex items-center gap-4 rounded-lg bg-card p-4 transition-shadow hover:shadow-[0_12px_32px_-12px_rgba(23,23,23,0.18)]"
            >
              <span className="relative size-16 shrink-0 overflow-hidden rounded-md bg-placeholder">
                {order.image && <Image src={order.image} alt="" fill sizes="64px" className="object-cover" />}
              </span>
              <span className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex flex-wrap items-center gap-2 text-sm">
                  <strong className="tabular-nums">{order.number}</strong>
                  <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-primary">{order.status_label}</span>
                </span>
                <span className="truncate text-sm">
                  {order.items_count > 1 ? t("itemsMore", { title: order.title ?? "", count: formatNumber(order.items_count - 1, locale) }) : order.title}
                </span>
                <span className="text-xs text-muted-foreground">{date.format(new Date(order.created_at))}</span>
              </span>
              <span className="flex items-center gap-1 text-sm font-semibold tabular-nums">
                {formatTaka(order.total, locale)}
                <ChevronRight className="size-4 text-muted-foreground" />
              </span>
            </Link>
          </li>
        ))}
      </ul>
      {query.hasNextPage && (
        <button type="button" onClick={() => query.fetchNextPage()} disabled={query.isFetchingNextPage} className={cn(buttonVariants({ variant: "outline" }), "h-11 self-center rounded-full px-6")}>
          {query.isFetchingNextPage && <Loader2 className="size-4 animate-spin" />}
          {t("loadMore")}
        </button>
      )}
    </div>
  );
}
