import { getLocale, getTranslations } from "next-intl/server";
import { getProductReviews } from "@/lib/api/catalog";
import type { Product } from "@/lib/api/types";
import { formatNumber } from "@/lib/format";
import { ReviewList } from "./review-list";
import { Stars } from "./stars";
import { WriteReview } from "./write-review";

/** Summary, star breakdown and the first page of approved reviews. */
export async function ProductReviews({ product }: { product: Product }) {
  const [t, locale] = await Promise.all([getTranslations("reviews"), getLocale()]);
  const first = product.reviews_count > 0 ? await getProductReviews(product.slug, locale) : null;
  const total = product.reviews_count;

  return (
    <section id="reviews" className="grid scroll-mt-28 gap-10 border-t border-border pt-12 md:grid-cols-[280px_1fr]">
      <div className="flex flex-col gap-5">
        <h2 className="text-2xl font-semibold">{t("title")}</h2>
        {total > 0 && (
          <div className="flex flex-col gap-4">
            <div className="flex items-end gap-3">
              <strong className="font-heading text-5xl leading-none font-semibold tabular-nums">{formatNumber(product.rating_avg, locale)}</strong>
              <span className="flex flex-col gap-1 pb-0.5">
                <Stars value={product.rating_avg} className="text-lg" label={t("ratingAria", { rating: formatNumber(product.rating_avg, locale) })} />
                <span className="text-sm text-muted-foreground">{t("count", { count: formatNumber(total, locale) })}</span>
              </span>
            </div>
            <ul className="flex flex-col gap-1.5 text-sm">
              {product.rating_breakdown.map(({ stars, count }) => (
                <li key={stars} className="flex items-center gap-3 tabular-nums">
                  <span className="w-8 text-muted-foreground">{formatNumber(stars, locale)}★</span>
                  <span className="h-2 flex-1 overflow-hidden rounded-full bg-secondary">
                    <span className="block h-full origin-left rounded-full bg-gold" style={{ transform: `scaleX(${count / total})` }} />
                  </span>
                  <span className="w-8 text-right text-muted-foreground">{formatNumber(count, locale)}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
        <WriteReview product={{ id: product.id, slug: product.slug, name: product.name, image: product.image }} />
      </div>

      {first && first.data.length > 0 ? (
        <ReviewList slug={product.slug} initial={first} />
      ) : (
        <p className="rounded-lg bg-card px-6 py-12 text-center text-muted-foreground">{t("noReviews")}</p>
      )}
    </section>
  );
}
