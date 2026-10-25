"use client";

import useEmblaCarousel from "embla-carousel-react";
import Image from "next/image";
import { useReducedMotion } from "motion/react";
import { useEffect, useState } from "react";
import type { Banner } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { BannerLink } from "./banner-link";

const INTERVAL = 5000;

/** Admin-managed hero slider: swipe, dots, and a gentle autoplay that pauses on hover/focus. */
export function BannerSlider({ banners }: { banners: Banner[] }) {
  const reduce = useReducedMotion();
  const [emblaRef, embla] = useEmblaCarousel({ loop: banners.length > 1, duration: 30 });
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);

  useEffect(() => {
    if (!embla) return;
    const onSelect = () => setIndex(embla.selectedScrollSnap());
    embla.on("select", onSelect);
    return () => {
      embla.off("select", onSelect);
    };
  }, [embla]);

  useEffect(() => {
    if (!embla || reduce || paused || banners.length < 2) return;
    const timer = setInterval(() => embla.scrollNext(), INTERVAL);
    return () => clearInterval(timer);
  }, [embla, reduce, paused, banners.length]);

  return (
    <section
      aria-roledescription="carousel"
      className="mx-auto w-full max-w-6xl px-4 pt-6 md:pt-8"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
      <div className="relative overflow-hidden rounded-lg bg-placeholder" ref={emblaRef}>
        <div className="flex">
          {banners.map((banner, i) => (
            <div key={banner.id} className="min-w-0 flex-[0_0_100%]" aria-roledescription="slide" aria-label={`${i + 1} / ${banners.length}`}>
              <BannerLink href={banner.link} className={cn("relative block aspect-[5/2]", banner.image_mobile && "max-md:aspect-square")}>
                <Image
                  src={banner.image}
                  alt={banner.title}
                  fill
                  priority={i === 0}
                  sizes="(min-width: 1152px) 1120px, 100vw"
                  className={cn("object-cover", banner.image_mobile && "max-md:hidden")}
                />
                {banner.image_mobile && (
                  <Image src={banner.image_mobile} alt={banner.title} fill priority={i === 0} sizes="100vw" className="object-cover md:hidden" />
                )}
              </BannerLink>
            </div>
          ))}
        </div>

        {banners.length > 1 && (
          <div className="absolute inset-x-0 bottom-3 flex justify-center gap-1.5 md:bottom-5">
            {banners.map((banner, i) => (
              <button
                key={banner.id}
                type="button"
                onClick={() => embla?.scrollTo(i)}
                aria-label={banner.title}
                aria-current={i === index}
                className="grid h-6 place-items-center px-0.5"
              >
                <span
                  className={cn(
                    "block h-1.5 w-6 origin-center rounded-full bg-card shadow-sm transition-[transform,opacity] duration-300",
                    i === index ? "scale-x-100 opacity-100" : "scale-x-[0.3] opacity-60",
                  )}
                />
              </button>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
