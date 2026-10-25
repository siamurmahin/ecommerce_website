import type { Metadata } from "next";
import { PackageSearch } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { TrackOrderForm } from "@/components/order/track-order-form";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("track");
  return { title: t("title"), robots: { index: false } };
}

export default async function TrackOrderPage() {
  const t = await getTranslations("track");

  return (
    <main className="mx-auto flex w-full max-w-lg flex-1 flex-col gap-6 px-4 pt-10 pb-16 md:pt-16">
      <div className="flex flex-col items-center gap-3 text-center">
        <span className="grid size-14 place-items-center rounded-full bg-secondary text-primary">
          <PackageSearch className="size-6" strokeWidth={1.6} />
        </span>
        <h1 className="text-3xl font-semibold tracking-tight">{t("title")}</h1>
        <p className="max-w-sm text-muted-foreground">{t("note")}</p>
      </div>
      <TrackOrderForm />
    </main>
  );
}
