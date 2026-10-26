import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { Phone } from "lucide-react";
import { LandingBlockView } from "@/components/landing/landing-blocks";
import { LandingView } from "@/components/landing/landing-view";
import { StickyOrderBar } from "@/components/landing/sticky-order-bar";
import { lookFor } from "@/components/landing/templates";
import { Reveal } from "@/components/motion/reveal";
import { BrandMark } from "@/components/layout/brand-mark";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { getCheckoutOptions } from "@/lib/api/checkout";
import { getLandingPage, getLandingSlugs, getSettings } from "@/lib/api/content";
import { formatTaka } from "@/lib/format";
import { alternates } from "@/lib/site";
import { cn } from "@/lib/utils";

// Ad landing pages at /l/{slug}: admin-built blocks with the order form on the page. The site header,
// footer and bottom nav are hidden here (see `[data-landing]` in globals.css) so the visitor stays on the offer.

export async function generateStaticParams() {
  const slugs = (await getLandingSlugs()).map(({ slug }) => ({ slug }));
  // Cache Components needs at least one param to validate the route; the placeholder 404s.
  return slugs.length > 0 ? slugs : [{ slug: "__placeholder__" }];
}

export async function generateMetadata({ params }: PageProps<"/[locale]/l/[slug]">): Promise<Metadata> {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const page = await getLandingPage(slug, locale);
  if (!page) return {};
  return {
    title: { absolute: page.meta.title ?? page.title },
    description: page.meta.description,
    // An A/B variant points search engines and shares at the main page.
    alternates: alternates(`/l/${page.variant_of ?? slug}`, locale),
    robots: page.indexable ? undefined : { index: false, follow: false },
    openGraph: { type: "website", title: page.meta.title ?? page.title, images: page.meta.image ? [page.meta.image] : [] },
  };
}

export default function LandingPage(props: PageProps<"/[locale]/l/[slug]">) {
  return (
    <main data-landing className="flex flex-1 flex-col">
      <Suspense fallback={<LandingFallback />}>
        <LandingContent {...props} />
      </Suspense>
    </main>
  );
}

async function LandingContent({ params }: PageProps<"/[locale]/l/[slug]">) {
  const [{ slug }, locale] = await Promise.all([params, rootLocale()]);
  const [t, page, options, settings] = await Promise.all([getTranslations("landing"), getLandingPage(slug, locale), getCheckoutOptions(locale), getSettings(locale)]);
  if (!page) notFound();

  const hasForm = page.blocks.some((b) => b.type === "order_form");
  const look = lookFor(page.template);
  const announcement = page.blocks.find((b) => b.type === "announcement");
  const blocks = page.blocks.filter((b) => b.type !== "announcement");
  const hotline = settings.hotline && settings.hotline_tel ? { label: settings.hotline, tel: settings.hotline_tel } : null;

  return (
    <>
      {announcement?.title && (
        <p data-block="announcement" className="bg-primary px-4 py-2.5 text-center text-xs font-semibold tracking-wide text-primary-foreground sm:text-sm">
          <span aria-hidden className="mr-2 inline-block size-1.5 -translate-y-px rounded-full bg-gold align-middle" />
          {announcement.title}
        </p>
      )}
      <header className="border-b border-border/70 bg-background/85 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
          <Link href="/" aria-label={settings.brand.name}>
            <BrandMark brand={settings.brand} className="text-xl" priority />
          </Link>
          {settings.hotline && (
            <a href={`tel:${settings.hotline_tel}`} className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm font-semibold tabular-nums hover:border-primary">
              <Phone className="size-4 text-primary" strokeWidth={1.8} />
              <span className="sr-only sm:not-sr-only">{t("call")}</span>
              {settings.hotline}
            </a>
          )}
        </div>
      </header>

      <LandingView slug={page.slug} />
      <div data-template={page.template} className={cn("flex flex-col pt-2 pb-16", look.gap)}>
        {blocks.map((block, i) => {
          // Story design: blocks alternate between the page colour and full-width white bands; the hero keeps the page colour.
          const onBand = look.bands && i % 2 === 1;
          const view = <LandingBlockView block={block} product={page.product} options={options} landingPageId={page.id} look={look} onBand={onBand} hotline={hotline} />;
          return (
            <div key={block.id} data-block={block.type} className={cn(look.bands && "py-12 md:py-20", look.bands && i === 0 && "pt-2 md:pt-4", onBand && "bg-card")}>
              {/* The first block is the page's largest paint, so it isn't held back by an entrance animation. */}
              {i === 0 ? view : <Reveal>{view}</Reveal>}
            </div>
          );
        })}
      </div>

      <footer className="mt-auto border-t border-border/70 py-6 text-center text-xs text-muted-foreground">
        © {settings.brand.name}
        {settings.hotline && (
          <>
            {" · "}
            <a href={`tel:${settings.hotline_tel}`} className="tabular-nums hover:text-foreground">
              {settings.hotline}
            </a>
          </>
        )}
      </footer>

      {hasForm && page.product && (
        <StickyOrderBar>
          <a href="#order" className={cn(buttonVariants({ size: "lg" }), "h-13 w-full rounded-full text-base shadow-[0_12px_32px_-12px_rgba(23,23,23,0.45)]")}>
            {t("orderNow")} · <span className="tabular-nums">{formatTaka(page.product.price, locale)}</span>
          </a>
        </StickyOrderBar>
      )}
    </>
  );
}

function LandingFallback() {
  return (
    <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 pt-24 md:grid-cols-2" aria-hidden>
      <div className="aspect-square animate-pulse rounded-lg bg-placeholder md:order-2" />
      <div className="flex flex-col gap-4">
        <div className="h-10 w-3/4 animate-pulse rounded-md bg-placeholder" />
        <div className="h-4 w-full animate-pulse rounded-md bg-placeholder" />
        <div className="h-4 w-2/3 animate-pulse rounded-md bg-placeholder" />
        <div className="h-12 w-48 animate-pulse rounded-full bg-placeholder" />
      </div>
    </div>
  );
}
