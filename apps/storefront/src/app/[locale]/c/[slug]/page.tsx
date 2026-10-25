import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { ProductGridSkeleton } from "@/components/commerce/product-grid";
import { PageHeader } from "@/components/listing/page-header";
import { ProductListing } from "@/components/listing/product-listing";
import { JsonLd } from "@/components/seo/json-ld";
import { Link } from "@/i18n/navigation";
import { getCategories, getCategory } from "@/lib/api/catalog";
import { routing } from "@/i18n/routing";
import { breadcrumbSchema } from "@/lib/schema";
import { alternates } from "@/lib/site";

export async function generateStaticParams() {
  const categories = await getCategories(routing.defaultLocale);
  return categories.flatMap((c) => [c, ...(c.children ?? [])]).map((c) => ({ slug: c.slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/c/[slug]">): Promise<Metadata> {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const [category, t] = await Promise.all([getCategory(slug, locale), getTranslations({ locale, namespace: "meta" })]);
  if (!category) return {};
  return {
    title: category.meta.title || category.name,
    description: category.meta.description || category.description || t("categoryDescription", { name: category.name ?? "" }),
    alternates: alternates(`/c/${slug}`, locale),
  };
}

export default function CategoryPage(props: PageProps<"/[locale]/c/[slug]">) {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4">
      <Suspense fallback={<ListingFallback />}>
        <CategoryContent {...props} />
      </Suspense>
    </main>
  );
}

async function CategoryContent({ params, searchParams }: PageProps<"/[locale]/c/[slug]">) {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const [t, category] = await Promise.all([getTranslations("nav"), getCategory(slug, locale)]);
  if (!category) notFound();

  return (
    <>
      <JsonLd data={breadcrumbSchema([[t("home"), "/"], [t("categories"), "/categories"], [category.name ?? slug, `/c/${slug}`]], locale)} />
      <PageHeader title={category.name ?? slug} description={category.description} crumbs={[{ href: "/", label: t("home") }, { href: "/categories", label: t("categories") }]} />
      {category.children && category.children.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {category.children.map((child) => (
            <li key={child.id}>
              <Link href={`/c/${child.slug}`} className="inline-block rounded-full border border-border bg-card px-4 py-2 text-sm transition-colors hover:border-primary hover:text-primary">
                {child.name}
              </Link>
            </li>
          ))}
        </ul>
      )}
      <Suspense fallback={<ProductGridSkeleton />}>
        <ProductListing base={{ category: slug }} searchParams={searchParams} pathname={`/c/${slug}`} locale={locale} />
      </Suspense>
    </>
  );
}

function ListingFallback() {
  return (
    <div className="flex flex-col gap-6 pt-12">
      <div className="h-10 w-1/3 animate-pulse rounded-md bg-placeholder" />
      <ProductGridSkeleton />
    </div>
  );
}
