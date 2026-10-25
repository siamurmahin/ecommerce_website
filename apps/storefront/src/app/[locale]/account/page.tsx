import { Heart, PackageSearch } from "lucide-react";
import type { Metadata } from "next";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { AccountView } from "@/components/account/account-view";
import { PageHeader } from "@/components/listing/page-header";
import { Link } from "@/i18n/navigation";
import { getCheckoutOptions } from "@/lib/api/checkout";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("account");
  return { title: t("title"), robots: { index: false } };
}

/** Phone + OTP sign-in, then orders, addresses, reviews and profile. All account data loads in the browser. */
export default async function AccountPage(props: PageProps<"/[locale]/account">) {
  const [t, tn] = await Promise.all([getTranslations("account"), getTranslations("nav")]);
  const links = [
    { href: "/track-order", label: tn("trackOrder"), note: t("trackNote"), icon: PackageSearch },
    { href: "/wishlist", label: tn("wishlist"), note: t("wishlistNote"), icon: Heart },
  ];

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 px-4 pb-16">
      <PageHeader title={t("title")} crumbs={[{ href: "/", label: tn("home") }]} />
      <Suspense fallback={<div className="h-80 animate-pulse rounded-lg bg-card" aria-hidden />}>
        <Account {...props} />
      </Suspense>
      <ul className="grid gap-3 sm:grid-cols-2">
        {links.map(({ href, label, note, icon: Icon }) => (
          <li key={href}>
            <Link href={href} className="flex items-center gap-4 rounded-lg bg-card p-5 transition-shadow hover:shadow-[0_12px_32px_-12px_rgba(23,23,23,0.18)]">
              <Icon className="size-6 shrink-0 text-primary" strokeWidth={1.7} />
              <span className="flex flex-col">
                <span className="font-semibold">{label}</span>
                <span className="text-sm text-muted-foreground">{note}</span>
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}

async function Account({ searchParams }: PageProps<"/[locale]/account">) {
  const [params, options] = await Promise.all([searchParams, rootLocale().then(getCheckoutOptions)]);
  const one = (v: string | string[] | undefined) => (typeof v === "string" ? v : null);
  return <AccountView options={options} next={one(params.next)} initialTab={one(params.tab)} />;
}
