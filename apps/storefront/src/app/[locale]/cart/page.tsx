import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { getTranslations } from "next-intl/server";
import { CartView } from "@/components/cart/cart-view";
import { PageHeader } from "@/components/listing/page-header";
import { getCheckoutOptions } from "@/lib/api/checkout";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("cart");
  return { title: t("title"), robots: { index: false } };
}

export default async function CartPage() {
  const locale = await rootLocale();
  const [t, tn, options] = await Promise.all([getTranslations("cart"), getTranslations("nav"), getCheckoutOptions(locale)]);

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 pb-16">
      <PageHeader title={t("title")} crumbs={[{ href: "/", label: tn("home") }]} />
      <CartView freeDeliveryThreshold={options.free_delivery_threshold} />
    </main>
  );
}
