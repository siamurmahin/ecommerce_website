"use client";

import useEmblaCarousel from "embla-carousel-react";
import Image from "next/image";
import { AnimatePresence, motion } from "motion/react";
import { useCallback, useEffect, useMemo, useState } from "react";
import type { Product } from "@/lib/api/types";
import { cn } from "@/lib/utils";

type Props = { images: Product["images"]; activeImage?: string | null };

/** Swipeable on phones, thumbnail-driven on desktop. A variant image jumps to the front when selected. */
export function Gallery({ images, activeImage }: Props) {
  const slides = useMemo(
    () => (activeImage && !images.some((i) => i.url === activeImage) ? [{ url: activeImage, alt: images[0]?.alt ?? "" }, ...images] : images),
    [activeImage, images],
  );
  const [emblaRef, embla] = useEmblaCarousel({ loop: false });
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (!embla) return;
    const onSelect = () => setIndex(embla.selectedScrollSnap());
    embla.on("select", onSelect);
    return () => {
      embla.off("select", onSelect);
    };
  }, [embla]);

  useEffect(() => {
    if (!activeImage || !embla) return;
    const i = slides.findIndex((s) => s.url === activeImage);
    if (i >= 0) embla.scrollTo(i);
  }, [activeImage, embla, slides]);

  const go = useCallback((i: number) => embla?.scrollTo(i), [embla]);

  if (slides.length === 0) return <div className="aspect-[4/5] rounded-lg bg-placeholder" />;

  return (
    <div className="flex flex-col gap-3 md:flex-row-reverse md:gap-4 md:self-start">
      <div className="relative flex-1 overflow-hidden rounded-lg bg-placeholder" ref={emblaRef}>
        <div className="flex">
          {slides.map((img, i) => (
            <div key={img.url} className="relative aspect-[4/5] min-w-0 flex-[0_0_100%]">
              <Image src={img.url} alt={img.alt ?? ""} fill priority={i === 0} sizes="(min-width: 768px) 45vw, 100vw" className="object-cover" />
            </div>
          ))}
        </div>
        {slides.length > 1 && (
          <div className="absolute inset-x-0 bottom-4 flex justify-center gap-1.5 md:hidden">
            {slides.map((s, i) => (
              <span key={s.url} className={cn("h-1.5 rounded-full bg-card transition-all", i === index ? "w-5" : "w-1.5 opacity-60")} />
            ))}
          </div>
        )}
      </div>

      {slides.length > 1 && (
        <ul className="hidden w-20 shrink-0 flex-col gap-3 md:flex">
          {slides.map((img, i) => (
            <li key={img.url}>
              <button
                type="button"
                onClick={() => go(i)}
                aria-label={`${i + 1}`}
                className={cn(
                  "relative block aspect-[4/5] w-full overflow-hidden rounded-md bg-placeholder ring-offset-2 ring-offset-background transition",
                  i === index ? "ring-2 ring-primary" : "opacity-70 hover:opacity-100",
                )}
              >
                <Image src={img.url} alt="" fill sizes="80px" className="object-cover" />
                <AnimatePresence>{i === index && <motion.span layoutId="thumb" className="absolute inset-0" />}</AnimatePresence>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
