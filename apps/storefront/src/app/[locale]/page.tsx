import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { BannerSlider } from "@/components/home/banner-slider";
import { CategoryRail } from "@/components/home/category-rail";
import { HelpBand } from "@/components/home/help-band";
import { Hero } from "@/components/home/hero";
import { ProductSection } from "@/components/home/product-section";
import { PromoBanners } from "@/components/home/promo-banners";
import { TextSection } from "@/components/home/text-section";
import { TrustStrip } from "@/components/home/trust-strip";
import { JsonLd } from "@/components/seo/json-ld";
import { getHome } from "@/lib/api/catalog";
import { getSettings } from "@/lib/api/content";
import type { Banner, HomeSection, SiteSettings } from "@/lib/api/types";
import { organizationSchema, websiteSchema } from "@/lib/schema";
import { alternates } from "@/lib/site";

export async function generateMetadata(): Promise<Metadata> {
  return { alternates: alternates("/", await rootLocale()) };
}

/** Sections come from admin → কনটেন্ট → হোমপেজ বিল্ডার, in that order. */
export default async function HomePage() {
  const locale = await rootLocale();
  const [home, settings] = await Promise.all([getHome(locale), getSettings(locale)]);
  const ctx = { banners: home.banners, settings };

  // The trust strip sits snug under the hero rather than a full section gap away.
  const groups: HomeSection[][] = [];
  for (const section of home.sections) {
    const prev = groups.at(-1);
    if (section.type === "trust" && prev?.length === 1 && prev[0].type === "hero") prev.push(section);
    else groups.push([section]);
  }

  return (
    <main className="flex flex-1 flex-col gap-14 pb-8 md:gap-20">
      <JsonLd data={[organizationSchema(settings), websiteSchema(settings.brand.name, locale)]} />
      {groups.map((group) =>
        group.length > 1 ? (
          <div key={group[0].id} className="flex flex-col gap-5 md:gap-6">
            {group.map((section) => (
              <Section key={section.id} section={section} {...ctx} />
            ))}
          </div>
        ) : (
          <Section key={group[0].id} section={group[0]} {...ctx} />
        ),
      )}
    </main>
  );
}

function Section({ section, banners, settings }: { section: HomeSection; banners: { hero: Banner[]; promo: Banner[] }; settings: SiteSettings }) {
  switch (section.type) {
    case "hero":
      return banners.hero.length > 0 ? <BannerSlider banners={banners.hero} /> : <Hero section={section} />;
    case "trust":
      return <TrustStrip items={section.items} />;
    case "categories":
      return <CategoryRail title={section.title} categories={section.categories} />;
    case "products":
      return <ProductSection section={section} />;
    case "promo_banners":
      return <PromoBanners banners={banners.promo} />;
    case "help":
      return <HelpBand title={section.title} note={section.note} settings={settings} />;
    case "text":
      return <TextSection title={section.title} content={section.content} />;
  }
}
