import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";
import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getPage, getPageSlugs } from "@/lib/api/content";
import { alternates } from "@/lib/site";

// Admin-managed static pages (about, FAQ, policies…) at /{slug}. Static routes like /shop win over this.

export async function generateStaticParams() {
  return (await getPageSlugs()).map(({ slug }) => ({ page: slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/[page]">): Promise<Metadata> {
  const [{ page: slug }, locale] = await Promise.all([params, rootLocale()]);
  const page = await getPage(slug, locale);
  if (!page) return {};
  return { title: page.meta.title, description: page.meta.description, alternates: alternates(`/${slug}`, locale) };
}

export default function StaticPage(props: PageProps<"/[locale]/[page]">) {
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 px-4 pt-8 pb-16 md:pt-12">
      <Suspense fallback={<PageFallback />}>
        <PageContent {...props} />
      </Suspense>
    </main>
  );
}

async function PageContent({ params }: PageProps<"/[locale]/[page]">) {
  const [{ page: slug }, locale] = await Promise.all([params, rootLocale()]);
  const [t, page] = await Promise.all([getTranslations("nav"), getPage(slug, locale)]);
  if (!page) notFound();

  return (
    <>
      <nav aria-label="breadcrumb">
        <ol className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <li>
            <Link href="/" className="hover:text-foreground">
              {t("home")}
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li aria-current="page" className="text-foreground">
            {page.title}
          </li>
        </ol>
      </nav>
      <article className="flex flex-col gap-6 rounded-lg bg-card p-6 md:p-10">
        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">{page.title}</h1>
        <div className="prose-content text-[15px] leading-relaxed md:text-base" dangerouslySetInnerHTML={{ __html: page.content }} />
      </article>
    </>
  );
}

function PageFallback() {
  return (
    <div className="flex flex-col gap-4 rounded-lg bg-card p-6 md:p-10" aria-hidden>
      <div className="h-9 w-1/2 animate-pulse rounded-md bg-placeholder" />
      <div className="h-4 w-full animate-pulse rounded-md bg-placeholder" />
      <div className="h-4 w-5/6 animate-pulse rounded-md bg-placeholder" />
      <div className="h-4 w-2/3 animate-pulse rounded-md bg-placeholder" />
    </div>
  );
}
