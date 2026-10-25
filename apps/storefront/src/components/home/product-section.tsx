import { useTranslations } from "next-intl";
import { ProductCarousel } from "@/components/commerce/product-carousel";
import { Countdown } from "@/components/commerce/countdown";
import { ProductGrid } from "@/components/commerce/product-grid";
import type { HomeSection } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { SectionHeading } from "./section-heading";

/**
 * A homepage builder product row. `style`: plain, a soft grey panel, or the primary-colour
 * "highlight" panel (meant for deals — the one strong colour block on the page).
 */
export function ProductSection({ section }: { section: HomeSection }) {
  const t = useTranslations("home");
  const { title, note, products, link_url: href, layout, columns, style } = section;
  const badge = section.badge ?? undefined;
  if (products.length === 0) return null;

  const highlight = style === "highlight";
  const tone = highlight ? "inverse" : "default";
  const heading = { title: title ?? "", note: note ?? undefined, href: href ?? undefined, linkLabel: href ? t("viewAll") : undefined };

  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <div
        className={cn(
          "flex flex-col gap-6",
          style === "panel" && "-mx-4 rounded-none bg-secondary px-4 py-8 sm:mx-0 sm:rounded-lg sm:px-6 md:px-8 md:py-10",
          highlight && "rounded-lg bg-primary px-4 py-7 md:px-8 md:py-10",
        )}
      >
        {section.ends_at && <Countdown endsAt={section.ends_at} inverse={highlight} className={cn("-mb-2", highlight && "text-primary-foreground")} />}
        {layout === "carousel" ? (
          <ProductCarousel {...heading} products={products} tone={tone} badge={badge} />
        ) : (
          <>
            <SectionHeading {...heading} tone={tone} />
            <ProductGrid products={products} columns={columns} badge={badge} />
          </>
        )}
      </div>
    </section>
  );
}
