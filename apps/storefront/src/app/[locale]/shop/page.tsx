import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { ProductGridSkeleton } from "@/components/commerce/product-grid";
import { PageHeader } from "@/components/listing/page-header";
import { ProductListing } from "@/components/listing/product-listing";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("listing");
  return { title: t("allProducts") };
}

export default async function ShopPage({ searchParams }: PageProps<"/[locale]/shop">) {
  const locale = await rootLocale();
  const t = await getTranslations();

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4">
      <PageHeader title={t("listing.allProducts")} crumbs={[{ href: "/", label: t("nav.home") }]} />
      <Suspense fallback={<ProductGridSkeleton />}>
        <ProductListing base={{}} searchParams={searchParams} pathname="/shop" locale={locale} />
      </Suspense>
    </main>
  );
}
