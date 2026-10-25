"use client";

import useEmblaCarousel from "embla-carousel-react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";
import { Link } from "@/i18n/navigation";
import type { Category } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { SectionHeading } from "./section-heading";

/** Category slider: swipe/drag on any screen, arrow buttons on both edges while there is more to see. */
export function CategoryRail({ title, categories }: { title: string | null; categories: Category[] }) {
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

  const arrow =
    "absolute top-[calc(50%-1rem)] z-10 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-border bg-card/95 text-foreground shadow-[0_6px_20px_-8px_rgba(23,23,23,0.35)] backdrop-blur transition-[opacity,transform] duration-200 hover:border-primary hover:text-primary md:size-11";

  return (
    <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4">
      <SectionHeading title={title ?? t("shopByCategory")} href="/categories" linkLabel={t("viewAll")} />
      <div className="relative">
        <div className="-mx-4 overflow-hidden px-4 md:mx-0 md:px-0" ref={emblaRef}>
          <ul className="-ml-3 flex md:-ml-4">
            {categories.map((c) => (
              <li key={c.id} className="min-w-0 flex-[0_0_38%] pl-3 sm:flex-[0_0_26%] md:flex-[0_0_20%] md:pl-4 lg:flex-[0_0_16.6667%]">
                <Link href={`/c/${c.slug}`} className="group flex h-full flex-col gap-2.5 rounded-lg bg-card p-2 pb-3 transition-shadow duration-300 hover:shadow-[0_12px_32px_-12px_rgba(23,23,23,0.18)]" draggable={false}>
                  <span className="relative block aspect-square overflow-hidden rounded-md bg-placeholder">
                    {c.image && (
                      <Image
                        src={c.image}
                        alt=""
                        fill
                        sizes="(min-width: 1024px) 17vw, (min-width: 768px) 20vw, (min-width: 640px) 26vw, 38vw"
                        className="object-cover transition-transform duration-700 group-hover:scale-105"
                        draggable={false}
                      />
                    )}
                  </span>
                  <span className="line-clamp-2 text-center text-sm font-semibold transition-colors group-hover:text-primary">{c.name}</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <button
          type="button"
          onClick={() => embla?.scrollPrev()}
          aria-label={t("prev")}
          tabIndex={edges.prev ? 0 : -1}
          aria-hidden={!edges.prev}
          className={cn(arrow, "left-2", !edges.prev && "pointer-events-none scale-90 opacity-0")}
        >
          <ChevronLeft className="size-5" strokeWidth={1.8} />
        </button>
        <button
          type="button"
          onClick={() => embla?.scrollNext()}
          aria-label={t("next")}
          tabIndex={edges.next ? 0 : -1}
          aria-hidden={!edges.next}
          className={cn(arrow, "right-2", !edges.next && "pointer-events-none scale-90 opacity-0")}
        >
          <ChevronRight className="size-5" strokeWidth={1.8} />
        </button>
      </div>
    </section>
  );
}
