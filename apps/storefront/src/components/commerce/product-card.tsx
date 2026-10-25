import { Award, Clock, Flame, Sparkles, Star, Zap, type LucideIcon } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { ProductBadge, ProductCard as ProductCardData } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { CardBuyButtons, QuickViewButton } from "./card-actions";
import { WishlistButton } from "./wishlist-button";

type Props = {
  product: ProductCardData;
  priority?: boolean;
  /** Force a badge regardless of the product's own, e.g. every card in the best sellers section. */
  badge?: ProductBadge;
  className?: string;
};

const badgeIcons: Record<ProductBadge, LucideIcon> = {
  best_seller: Award,
  featured: Star,
  hot: Flame,
  new: Sparkles,
  limited: Clock,
};

const tag = "flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold shadow-xs sm:px-2 sm:text-[11px]";

export function ProductCard({ product, priority, badge = product.badge ?? undefined, className }: Props) {
  const t = useTranslations();
  const locale = useLocale();
  const discount =
    product.compare_price && product.compare_price > product.price
      ? Math.round(((product.compare_price - product.price) / product.compare_price) * 100)
      : null;
  const BadgeIcon = badge ? badgeIcons[badge] : null;

  return (
    <article
      className={cn(
        "group @container flex h-full flex-col rounded-lg border border-border/60 bg-card p-2 shadow-[0_4px_14px_-6px_rgba(23,23,23,0.12)] transition-shadow duration-300 hover:shadow-[0_14px_32px_-12px_rgba(23,23,23,0.22)] sm:p-2.5",
        className,
      )}
    >
      <div className="relative aspect-[4/5] overflow-hidden rounded-md bg-placeholder">
        <Link href={`/p/${product.slug}`} className="absolute inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
          {product.image && (
            <Image
              src={product.image}
              alt={product.name ?? ""}
              fill
              priority={priority}
              sizes="(min-width: 1024px) 22vw, 50vw"
              className={cn(
                "object-cover transition-transform duration-700 ease-[cubic-bezier(.22,1,.36,1)] group-hover:scale-[1.04]",
                !product.in_stock && "opacity-60",
              )}
            />
          )}
          {product.hover_image && product.in_stock && (
            <Image
              src={product.hover_image}
              alt=""
              fill
              sizes="(min-width: 1024px) 22vw, 50vw"
              className="object-cover opacity-0 transition-opacity duration-500 group-hover:opacity-100"
            />
          )}
        </Link>

        <div className="pointer-events-none absolute top-2 left-2 flex flex-col items-start gap-1">
          {!product.in_stock ? (
            <span className={cn(tag, "bg-foreground/80 text-background")}>{t("card.outOfStock")}</span>
          ) : (
            <>
              {product.flash_sale && (
                <span className={cn(tag, "bg-gold text-white")}>
                  <Zap className="size-3 shrink-0" strokeWidth={2.4} />
                  {t("flash.badge")}
                </span>
              )}
              {badge && BadgeIcon && !product.flash_sale && (
                <span className={cn(tag, badge === "best_seller" ? "bg-gold text-white" : "bg-card text-primary")}>
                  <BadgeIcon className="size-3 shrink-0" strokeWidth={2.4} />
                  {t(`badge.${badge}`)}
                </span>
              )}
              {discount && <span className={cn(tag, "bg-primary text-primary-foreground tabular-nums")}>−{formatNumber(discount, locale)}%</span>}
            </>
          )}
        </div>

        <WishlistButton product={product} className="absolute top-2 right-2" />

        {product.in_stock && (
          <QuickViewButton
            slug={product.slug}
            className="absolute inset-x-0 bottom-3 mx-auto w-fit translate-y-2 opacity-0 transition-[opacity,transform] duration-300 group-hover:translate-y-0 group-hover:opacity-100 focus-visible:translate-y-0 focus-visible:opacity-100 max-md:hidden"
          />
        )}
      </div>

      <div className="flex flex-1 flex-col gap-1 px-1 pt-2.5 pb-2.5 sm:px-1.5 sm:pt-3">
        <div className="flex min-h-4 items-center justify-between gap-2 text-[10px] sm:text-[11px]">
          {product.category && <span className="truncate font-bold tracking-wider text-muted-foreground uppercase">{product.category.name}</span>}
          {product.in_stock && product.low_stock ? (
            <span className="shrink-0 font-semibold text-gold tabular-nums">{t("card.left", { count: formatNumber(product.stock, locale) })}</span>
          ) : product.reviews_count > 0 ? (
            <span className="flex shrink-0 items-center gap-0.5 font-semibold tabular-nums" aria-label={t("reviews.ratingAria", { rating: formatNumber(product.rating_avg, locale) })}>
              <Star className="size-3 text-gold" fill="currentColor" strokeWidth={0} />
              {formatNumber(product.rating_avg, locale)}
              <span className="font-normal text-muted-foreground">({formatNumber(product.reviews_count, locale)})</span>
            </span>
          ) : product.sold_count > 0 ? (
            <span className="shrink-0 text-muted-foreground tabular-nums">{t("card.sold", { count: formatNumber(product.sold_count, locale) })}</span>
          ) : null}
        </div>
        {/* Narrow cards (phones, 3-up tablet grids) stack the price under the name; wide cards put it on the right. */}
        <div className="flex flex-col gap-1 @[15rem]:flex-row @[15rem]:items-start @[15rem]:justify-between @[15rem]:gap-2">
          <h3 className="line-clamp-2 min-h-[2.6em] min-w-0 flex-1 font-sans text-[13px] leading-tight font-bold sm:text-[15px]">
            <Link href={`/p/${product.slug}`} className="transition-colors hover:text-primary">
              {product.name}
            </Link>
          </h3>
          <div className="flex shrink-0 flex-wrap items-baseline gap-x-1.5 leading-tight tabular-nums @[15rem]:flex-col @[15rem]:items-end @[15rem]:gap-0">
            {product.price_from && <span className="text-[10px] text-muted-foreground">{t("product.from")}</span>}
            <strong className="text-[13px] font-bold whitespace-nowrap text-foreground sm:text-[15px]">{formatTaka(product.price, locale)}</strong>
            {product.compare_price && product.compare_price > product.price ? (
              <s className="text-[11px] whitespace-nowrap text-muted-foreground sm:text-xs">{formatTaka(product.compare_price, locale)}</s>
            ) : null}
          </div>
        </div>
      </div>

      <div className="mt-auto">
        <CardBuyButtons product={product} />
      </div>
    </article>
  );
}
