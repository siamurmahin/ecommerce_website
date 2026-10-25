import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { ProductGridSkeleton } from "@/components/commerce/product-grid";
import { SearchForm } from "@/components/layout/search-form";
import { PageHeader } from "@/components/listing/page-header";
import { ProductListing } from "@/components/listing/product-listing";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("nav");
  return { title: t("search"), robots: { index: false } };
}

export default async function SearchPage({ searchParams }: PageProps<"/[locale]/search">) {
  const locale = await rootLocale();
  const t = await getTranslations("nav");

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4">
      <PageHeader title={t("search")} />
      <SearchForm className="md:max-w-xl" autoFocus />
      <Suspense fallback={<ProductGridSkeleton />}>
        <ProductListing base={{}} searchParams={searchParams} pathname="/search" locale={locale} />
      </Suspense>
    </main>
  );
}
