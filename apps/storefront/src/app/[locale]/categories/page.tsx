import type { Metadata } from "next";
import Image from "next/image";
import { locale as rootLocale } from "next/root-params";
import { getTranslations } from "next-intl/server";
import { PageHeader } from "@/components/listing/page-header";
import { Reveal } from "@/components/motion/reveal";
import { Link } from "@/i18n/navigation";
import { getCategories } from "@/lib/api/catalog";
import { formatNumber } from "@/lib/format";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("nav");
  return { title: t("categories") };
}

export default async function CategoriesPage() {
  const locale = await rootLocale();
  const [t, tl, categories] = await Promise.all([getTranslations("nav"), getTranslations("listing"), getCategories(locale)]);

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4">
      <PageHeader title={t("categories")} crumbs={[{ href: "/", label: t("home") }]} />
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {categories.map((c, i) => (
          <Reveal as="li" key={c.id} delay={(i % 3) * 0.05}>
            <div className="flex h-full gap-4 rounded-lg border border-border bg-card p-4">
              <Link href={`/c/${c.slug}`} className="relative size-24 shrink-0 overflow-hidden rounded-md bg-placeholder">
                {c.image && <Image src={c.image} alt="" fill sizes="96px" className="object-cover" />}
              </Link>
              <div className="flex min-w-0 flex-col gap-1.5">
                <Link href={`/c/${c.slug}`} className="font-heading text-xl font-semibold hover:text-primary">
                  {c.name}
                </Link>
                <span className="text-xs text-muted-foreground tabular-nums">
                  {tl("products", { count: formatNumber((c.products_count ?? 0) + (c.children ?? []).reduce((s, x) => s + (x.products_count ?? 0), 0), locale) })}
                </span>
                {c.children && c.children.length > 0 && (
                  <ul className="flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    {c.children.map((child) => (
                      <li key={child.id}>
                        <Link href={`/c/${child.slug}`} className="hover:text-foreground">
                          {child.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </Reveal>
        ))}
      </ul>
    </main>
  );
}
