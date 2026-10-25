"use client";

import useEmblaCarousel from "embla-carousel-react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";
import { SectionHeading } from "@/components/home/section-heading";
import type { ProductBadge, ProductCard as ProductCardData } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { ProductCard } from "./product-card";

type Props = {
  title: string;
  note?: string;
  href?: string;
  linkLabel?: string;
  products: ProductCardData[];
  tone?: "default" | "inverse";
  /** Force a badge on every card. */
  badge?: ProductBadge;
};

/** Swipeable product row: peeks the next card on phones, arrow buttons on desktop. */
export function ProductCarousel({ title, note, href, linkLabel, products, tone = "default", badge }: Props) {
  const t = useTranslations("home");
  const [emblaRef, embla] = useEmblaCarousel({ align: "start", dragFree: true, containScroll: "trimSnaps" });
  const [edges, setEdges] = useState({ prev: false, next: false });

  const update = useCallback(() => {
    if (embla) setEdges({ prev: embla.canScrollPrev(), next: embla.canScrollNext() });
  }, [embla]);

  useEffect(() => {
    if (!embla) return;
    embla.on("select", update).on("reInit", update).on("scroll", update);
    queueMicrotask(update);
    return () => {
      embla.off("select", update).off("reInit", update).off("scroll", update);
    };
  }, [embla, update]);

  const arrow = cn(
    "hidden size-10 place-items-center rounded-full border transition-colors disabled:opacity-30 md:grid",
    tone === "inverse"
      ? "border-primary-foreground/30 text-primary-foreground hover:bg-primary-foreground/10"
      : "border-border bg-card hover:border-primary hover:text-primary",
  );

  return (
    <div className="flex flex-col gap-6">
      <SectionHeading title={title} note={note} href={href} linkLabel={linkLabel} tone={tone}>
        <button type="button" className={arrow} onClick={() => embla?.scrollPrev()} disabled={!edges.prev} aria-label={t("prev")}>
          <ChevronLeft className="size-5" strokeWidth={1.8} />
        </button>
        <button type="button" className={arrow} onClick={() => embla?.scrollNext()} disabled={!edges.next} aria-label={t("next")}>
          <ChevronRight className="size-5" strokeWidth={1.8} />
        </button>
      </SectionHeading>
      <div className="-mx-4 -my-3 overflow-hidden px-4 py-3 md:-mx-2 md:px-2" ref={emblaRef}>
        <ul className="-ml-3 flex md:-ml-5">
          {products.map((product) => (
            <li key={product.id} className="min-w-0 flex-[0_0_62%] pl-3 sm:flex-[0_0_40%] md:flex-[0_0_33.333%] md:pl-5 lg:flex-[0_0_25%]">
              <ProductCard product={product} badge={badge ?? product.badge ?? undefined} />
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
