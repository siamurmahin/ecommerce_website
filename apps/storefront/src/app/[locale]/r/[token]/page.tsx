import type { Metadata } from "next";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { RecoverCart } from "@/components/checkout/recover-cart";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("recover");
  return { title: t("title"), robots: { index: false } };
}

/** Link from the cart-reminder SMS: puts the saved cart back and opens checkout. */
export default function RecoverPage({ params }: PageProps<"/[locale]/r/[token]">) {
  return (
    <main className="mx-auto flex w-full max-w-xl flex-1 flex-col px-4 pt-16 pb-16">
      <Suspense fallback={null}>
        <RecoverCart params={params} />
      </Suspense>
    </main>
  );
}
