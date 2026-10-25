import type { ProductBadge, ProductCard as ProductCardData } from "@/lib/api/types";
import { Reveal } from "@/components/motion/reveal";
import { cn } from "@/lib/utils";
import { ProductCard } from "./product-card";

type Props = {
  products: ProductCardData[];
  priorityCount?: number;
  /** 5 fits a dense showcase row (best sellers); 4 is the default listing. */
  columns?: 4 | 5;
  badge?: ProductBadge;
};

const grid = "grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-5 lg:grid-cols-4";

export function ProductGrid({ products, priorityCount = 0, columns = 4, badge }: Props) {
  return (
    <ul className={cn(grid, columns === 5 && "lg:grid-cols-5 lg:gap-4")}>
      {products.map((product, i) =>
        // Above-the-fold cards render visible from the server HTML: a fade-in would hold the LCP image back until hydration.
        i < priorityCount ? (
          <li className="h-full" key={product.id}>
            <ProductCard product={product} priority badge={badge} />
          </li>
        ) : (
          <Reveal as="li" className="h-full" key={product.id} delay={Math.min(i % columns, 3) * 0.06}>
            <ProductCard product={product} badge={badge} />
          </Reveal>
        ),
      )}
    </ul>
  );
}

export function ProductGridSkeleton({ count = 8 }: { count?: number }) {
  return (
    <ul className={grid} aria-hidden>
      {Array.from({ length: count }, (_, i) => (
        <li key={i} className="flex flex-col gap-3">
          <div className="aspect-[4/5] animate-pulse rounded-lg bg-placeholder" />
          <div className="h-3 w-1/3 animate-pulse rounded bg-placeholder" />
          <div className="h-4 w-3/4 animate-pulse rounded bg-placeholder" />
        </li>
      ))}
    </ul>
  );
}
