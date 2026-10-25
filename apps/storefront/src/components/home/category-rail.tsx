import Image from "next/image";
import { useTranslations } from "next-intl";
import { Reveal } from "@/components/motion/reveal";
import { Link } from "@/i18n/navigation";
import type { Category } from "@/lib/api/types";
import { SectionHeading } from "./section-heading";

export function CategoryRail({ title, categories }: { title: string | null; categories: Category[] }) {
  const t = useTranslations("home");

  return (
    <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4">
      <SectionHeading title={title ?? t("shopByCategory")} href="/categories" linkLabel={t("viewAll")} />
      <ul className="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-7 md:gap-4 md:overflow-visible md:px-0">
        {categories.map((c, i) => (
          <Reveal as="li" key={c.id} delay={i * 0.04} className="w-[38%] shrink-0 snap-start sm:w-[28%] md:w-auto">
            <Link href={`/c/${c.slug}`} className="group flex flex-col gap-2.5 rounded-lg bg-card p-2 pb-3 transition-shadow duration-300 hover:shadow-[0_12px_32px_-12px_rgba(23,23,23,0.18)]">
              <span className="relative block aspect-square overflow-hidden rounded-md bg-placeholder">
                {c.image && (
                  <Image
                    src={c.image}
                    alt=""
                    fill
                    sizes="(min-width: 768px) 14vw, 38vw"
                    className="object-cover transition-transform duration-700 group-hover:scale-105"
                  />
                )}
              </span>
              <span className="text-center text-sm font-semibold transition-colors group-hover:text-primary">{c.name}</span>
            </Link>
          </Reveal>
        ))}
      </ul>
    </section>
  );
}
