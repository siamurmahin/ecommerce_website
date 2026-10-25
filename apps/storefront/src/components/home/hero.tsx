"use client";

import Image from "next/image";
import { motion, useReducedMotion } from "motion/react";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import type { HomeSection } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const ease = [0.22, 1, 0.36, 1] as const;

/** Copy rises in line by line; the two product photos settle in with a slow scale. */
export function Hero({ section }: { section: HomeSection }) {
  const reduce = useReducedMotion();
  const [main, side] = section.products;

  const rise = (i: number) =>
    reduce ? {} : { initial: { opacity: 0, y: 24 }, animate: { opacity: 1, y: 0 }, transition: { duration: 0.7, delay: 0.08 * i, ease } };

  return (
    <section className="mx-auto grid max-w-6xl gap-8 px-4 pt-8 md:grid-cols-[1.05fr_1fr] md:items-center md:gap-12 md:pt-14">
      <div className="flex flex-col gap-5">
        {section.eyebrow && (
          <motion.span {...rise(0)} className="text-xs font-bold tracking-[0.16em] text-gold uppercase">
            {section.eyebrow}
          </motion.span>
        )}
        <motion.h1 {...rise(1)} className="text-[40px] leading-[1.1] font-semibold tracking-tight md:text-6xl">
          {section.title}
        </motion.h1>
        {section.subtitle && (
          <motion.p {...rise(2)} className="max-w-md text-base leading-relaxed text-muted-foreground md:text-lg">
            {section.subtitle}
          </motion.p>
        )}
        {section.button_label && (
          <motion.div {...rise(3)} className="flex flex-wrap gap-3 pt-2">
            <Link href={section.button_url || "/shop"} className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full px-7 text-[15px]")}>
              {section.button_label}
            </Link>
          </motion.div>
        )}
      </div>

      <div className="grid grid-cols-[1.5fr_1fr] gap-3 md:gap-4">
        {[main, side].map((p, i) =>
          p ? (
            <motion.div
              key={p.id}
              initial={reduce ? false : { opacity: 0, scale: 1.06 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 1.1, delay: 0.15 + i * 0.12, ease }}
              className={cn("relative overflow-hidden rounded-lg bg-placeholder", i === 0 ? "aspect-[4/5]" : "mt-12 aspect-[3/4] self-start")}
            >
              <Link href={`/p/${p.slug}`} className="absolute inset-0">
                {p.image && <Image src={p.image} alt={p.name ?? ""} fill priority sizes="(min-width: 768px) 30vw, 60vw" className="object-cover" />}
                <span className="sr-only">{p.name}</span>
              </Link>
            </motion.div>
          ) : (
            <div key={i} className="aspect-[4/5] rounded-lg bg-placeholder" />
          ),
        )}
      </div>
    </section>
  );
}
