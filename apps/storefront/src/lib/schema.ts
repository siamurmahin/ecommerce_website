import type { Product, SiteSettings } from "@/lib/api/types";
import { absoluteUrl, SITE_URL } from "@/lib/site";

const taka = (poisha: number) => (poisha / 100).toFixed(2);

export function organizationSchema(settings: SiteSettings) {
  return {
    "@context": "https://schema.org",
    "@type": "OnlineStore",
    "@id": `${SITE_URL}/#organization`,
    name: settings.brand.name,
    url: SITE_URL,
    ...(settings.brand.logo ? { logo: settings.brand.logo } : {}),
    ...(settings.footer_about ? { description: settings.footer_about } : {}),
    ...(settings.email ? { email: settings.email } : {}),
    telephone: settings.hotline,
    ...(settings.address ? { address: { "@type": "PostalAddress", streetAddress: settings.address, addressCountry: "BD" } } : {}),
    contactPoint: { "@type": "ContactPoint", telephone: settings.hotline, contactType: "customer service", areaServed: "BD", availableLanguage: ["bn", "en"] },
    sameAs: settings.socials.map((s) => s.url),
  };
}

export function websiteSchema(brandName: string, locale: string) {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "@id": `${SITE_URL}/#website`,
    name: brandName,
    url: absoluteUrl("/", locale),
    inLanguage: locale,
    publisher: { "@id": `${SITE_URL}/#organization` },
    potentialAction: {
      "@type": "SearchAction",
      target: { "@type": "EntryPoint", urlTemplate: `${absoluteUrl("/search", locale)}?q={search_term_string}` },
      "query-input": "required name=search_term_string",
    },
  };
}

export function productSchema(product: Product, locale: string) {
  const url = absoluteUrl(`/p/${product.slug}`, locale);
  const variants = product.variants.filter((v) => v.price > 0);
  const prices = variants.length > 0 ? variants.map((v) => v.price) : [product.price];
  const low = Math.min(...prices);
  const high = Math.max(...prices);
  const availability = `https://schema.org/${product.in_stock ? "InStock" : "OutOfStock"}`;

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    url,
    image: product.images.length > 0 ? product.images.map((i) => i.url) : product.image ? [product.image] : undefined,
    description: product.meta.description || undefined,
    sku: product.sku || undefined,
    ...(product.brand ? { brand: { "@type": "Brand", name: product.brand.name } } : {}),
    ...(product.category ? { category: product.category.name } : {}),
    ...(product.reviews_count > 0
      ? { aggregateRating: { "@type": "AggregateRating", ratingValue: product.rating_avg, reviewCount: product.reviews_count, bestRating: 5, worstRating: 1 } }
      : {}),
    offers:
      low === high
        ? { "@type": "Offer", url, priceCurrency: "BDT", price: taka(low), availability, itemCondition: "https://schema.org/NewCondition", seller: { "@id": `${SITE_URL}/#organization` } }
        : { "@type": "AggregateOffer", url, priceCurrency: "BDT", lowPrice: taka(low), highPrice: taka(high), offerCount: variants.length, availability },
  };
}

/** `crumbs` are [label, path] pairs from the home page down to the current page. */
export function breadcrumbSchema(crumbs: [label: string, path: string][], locale: string) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: crumbs.map(([name, path], i) => ({ "@type": "ListItem", position: i + 1, name, item: absoluteUrl(path, locale) })),
  };
}
