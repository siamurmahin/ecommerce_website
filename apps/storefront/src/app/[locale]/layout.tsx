import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { notFound } from "next/navigation";
import { hasLocale, NextIntlClientProvider } from "next-intl";
import { getTranslations } from "next-intl/server";
import { CartDrawer } from "@/components/cart/cart-drawer";
import { QuickView } from "@/components/commerce/quick-view";
import { AttributionCapture } from "@/components/checkout/attribution-capture";
import { BottomNav } from "@/components/layout/bottom-nav";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { SmoothScroll } from "@/components/motion/smooth-scroll";
import { QueryProvider } from "@/components/providers/query-provider";
import { WishlistSync } from "@/components/wishlist/wishlist-sync";
import { TrackingScripts } from "@/components/tracking/tracking-scripts";
import { routing } from "@/i18n/routing";
import { getCheckoutOptions } from "@/lib/api/checkout";
import { getSettings, getTracking } from "@/lib/api/content";
import { fontVariables } from "@/lib/fonts";
import { themeCss } from "@/lib/theme";
import { SITE_URL } from "@/lib/site";
import "../globals.css";


export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const [t, tracking, { brand }] = await Promise.all([getTranslations("meta"), getTracking(), getSettings(locale)]);

  return {
    metadataBase: new URL(SITE_URL),
    title: {
      default: brand.tagline ? `${brand.name} — ${brand.tagline}` : brand.name,
      template: `%s · ${brand.name}`,
    },
    applicationName: brand.name,
    icons: { icon: brand.favicon ?? "/favicon.ico", apple: "/pwa-icons/apple.png" },
    appleWebApp: { capable: true, title: brand.name, statusBarStyle: "default" },
    description: t("description"),
    verification: {
      ...(tracking.gsc_verification ? { google: tracking.gsc_verification } : {}),
      ...(tracking.fb_domain_verification ? { other: { "facebook-domain-verification": tracking.fb_domain_verification } } : {}),
    },
  };
}

export default async function LocaleLayout({ children }: LayoutProps<"/[locale]">) {
  const locale = await rootLocale();
  if (!hasLocale(routing.locales, locale)) notFound();
  const [{ free_delivery_threshold, zones }, tracking, settings] = await Promise.all([getCheckoutOptions(locale), getTracking(), getSettings(locale)]);

  return (
    <html
      lang={locale}
      className={`${fontVariables} h-full antialiased`}
    >
      <head>
        <style id="theme" dangerouslySetInnerHTML={{ __html: themeCss(settings.theme) }} />
      </head>
      <body className="min-h-full flex flex-col pb-24 md:pb-0">
        <TrackingScripts config={tracking} />
        <NextIntlClientProvider>
          <QueryProvider>
            <SiteHeader />
            {children}
            <SiteFooter />
            <BottomNav />
            <CartDrawer freeDeliveryThreshold={free_delivery_threshold} />
            <QuickView zones={zones} />
            <SmoothScroll />
            <AttributionCapture />
            <WishlistSync />
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
