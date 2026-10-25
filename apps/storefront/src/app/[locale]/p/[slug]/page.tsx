import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { ProductGrid, ProductGridSkeleton } from "@/components/commerce/product-grid";
import { SectionHeading } from "@/components/home/section-heading";
import { BundleOffer } from "@/components/product/bundle-offer";
import { ProductPurchase } from "@/components/product/product-purchase";
import { ProductQuestions } from "@/components/questions/product-questions";
import { ProductReviews } from "@/components/reviews/product-reviews";
import { JsonLd } from "@/components/seo/json-ld";
import { Link } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { getProduct, getProducts, getRelated } from "@/lib/api/catalog";
import { getCheckoutOptions } from "@/lib/api/checkout";
import { getSettings } from "@/lib/api/content";
import { breadcrumbSchema, productSchema } from "@/lib/schema";
import { alternates } from "@/lib/site";

export async function generateStaticParams() {
  const { data } = await getProducts({ sort: "popular", perPage: 12 }, routing.defaultLocale);
  return data.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/p/[slug]">): Promise<Metadata> {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const product = await getProduct(slug, locale);
  if (!product) return {};
  return {
    title: product.meta.title,
    description: product.meta.description,
    alternates: alternates(`/p/${slug}`, locale),
    openGraph: { type: "website", images: product.image ? [product.image] : [] },
  };
}

export default function ProductPage(props: PageProps<"/[locale]/p/[slug]">) {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-16 px-4 pt-6 md:pt-10">
      <Suspense fallback={<ProductFallback />}>
        <ProductContent {...props} />
      </Suspense>
    </main>
  );
}

async function ProductContent({ params }: PageProps<"/[locale]/p/[slug]">) {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const [t, product, options, settings] = await Promise.all([getTranslations(), getProduct(slug, locale), getCheckoutOptions(locale), getSettings(locale)]);
  if (!product) notFound();

  return (
    <>
      <nav aria-label="breadcrumb" className="-mb-8 md:-mb-12">
        <ol className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
          <li>
            <Link href="/" className="hover:text-foreground">
              {t("nav.home")}
            </Link>
          </li>
          {product.category && (
            <li className="flex items-center gap-1.5">
              <span aria-hidden>/</span>
              <Link href={`/c/${product.category.slug}`} className="hover:text-foreground">
                {product.category.name}
              </Link>
            </li>
          )}
        </ol>
      </nav>

      <JsonLd
        data={[
          productSchema(product, locale),
          breadcrumbSchema(
            [[t("nav.home"), "/"], ...(product.category ? [[product.category.name ?? "", `/c/${product.category.slug}`] as [string, string]] : []), [product.name ?? slug, `/p/${slug}`]],
            locale,
          ),
        ]}
      />
      <ProductPurchase product={product} zones={options.zones} contact={settings} />

      {product.bundles.length > 0 && (
        <section className="flex flex-col gap-6">
          <SectionHeading title={t("bundle.title")} />
          <div className="grid gap-5 lg:grid-cols-2">
            {product.bundles.map((bundle) => (
              <BundleOffer key={bundle.id} bundle={bundle} />
            ))}
          </div>
        </section>
      )}

      {product.upsells.length > 0 && (
        <section className="flex flex-col gap-6">
          <SectionHeading title={t("product.upsells")} />
          <ProductGrid products={product.upsells.slice(0, 4)} />
        </section>
      )}

      {(product.description || product.specifications.length > 0) && (
        <section className="grid gap-10 border-t border-border pt-12 md:grid-cols-[1.4fr_1fr]">
          {product.description && (
            <div className="flex flex-col gap-4">
              <h2 className="text-2xl font-semibold">{t("product.description")}</h2>
              {/* Authored by staff in the admin rich editor. */}
              <div className="prose-content max-w-[65ch] leading-relaxed text-foreground/85" dangerouslySetInnerHTML={{ __html: product.description }} />
            </div>
          )}
          {product.specifications.length > 0 && (
            <div className="flex flex-col gap-4">
              <h2 className="text-2xl font-semibold">{t("product.specifications")}</h2>
              <dl className="divide-y divide-border rounded-lg border border-border bg-card">
                {product.specifications.map((s) => (
                  <div key={s.label} className="grid grid-cols-2 gap-4 px-4 py-3 text-sm">
                    <dt className="text-muted-foreground">{s.label}</dt>
                    <dd className="font-medium">{s.value}</dd>
                  </div>
                ))}
              </dl>
            </div>
          )}
        </section>
      )}

      <ProductReviews product={product} />

      <ProductQuestions slug={product.slug} />

      <Suspense fallback={<ProductGridSkeleton count={4} />}>
        <Related slug={slug} locale={locale} title={t("product.related")} />
      </Suspense>
    </>
  );
}

async function Related({ slug, locale, title }: { slug: string; locale: string; title: string }) {
  const products = await getRelated(slug, locale);
  if (products.length === 0) return null;

  return (
    <section className="flex flex-col gap-6">
      <SectionHeading title={title} />
      <ProductGrid products={products.slice(0, 4)} />
    </section>
  );
}

function ProductFallback() {
  return (
    <div className="grid gap-8 md:grid-cols-2 md:gap-12" aria-hidden>
      <div className="aspect-[4/5] animate-pulse rounded-lg bg-placeholder" />
      <div className="flex flex-col gap-4">
        <div className="h-10 w-3/4 animate-pulse rounded-md bg-placeholder" />
        <div className="h-8 w-1/3 animate-pulse rounded-md bg-placeholder" />
        <div className="h-24 animate-pulse rounded-md bg-placeholder" />
      </div>
    </div>
  );
}
