import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { PageHeader } from "@/components/listing/page-header";
import { WishlistView } from "@/components/wishlist/wishlist-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("wishlist");
  return { title: t("title"), robots: { index: false } };
}

export default async function WishlistPage() {
  const [t, tn] = await Promise.all([getTranslations("wishlist"), getTranslations("nav")]);

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 pb-16">
      <PageHeader title={t("title")} crumbs={[{ href: "/", label: tn("home") }]} />
      <WishlistView />
    </main>
  );
}
