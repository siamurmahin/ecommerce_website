import { Star } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { Countdown } from "@/components/commerce/countdown";
import { trustIcons } from "@/components/home/trust-strip";
import { buttonVariants } from "@/components/ui/button";
import type { CheckoutOptions, LandingBlock, Product } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { LandingOrder } from "./landing-order";

type Props = { block: LandingBlock; product: Product | null; options: CheckoutOptions; landingPageId: number };

/** One admin-built block. Every button points at the order form (#order). */
export function LandingBlockView({ block, product, options, landingPageId }: Props) {
  switch (block.type) {
    case "hero":
      return <Hero block={block} product={product} />;
    case "benefits":
      return <Benefits block={block} />;
    case "gallery":
      return <Gallery block={block} />;
    case "video":
      return <Video block={block} />;
    case "text":
      return (
        <Section title={block.title}>
          {block.content && <div className="prose-content rounded-lg bg-card p-6 text-[15px] leading-relaxed md:p-8 md:text-base" dangerouslySetInnerHTML={{ __html: block.content }} />}
        </Section>
      );
    case "reviews":
      return <Reviews block={block} />;
    case "faq":
      return <Faq block={block} />;
    case "cta":
      return <Cta block={block} />;
    case "order_form":
      return (
        <section id="order" className="mx-auto w-full max-w-6xl scroll-mt-6 px-4">
          <OrderHeading block={block} />
          <LandingOrder product={product} options={options} landingPageId={landingPageId} />
        </section>
      );
  }
}

function Section({ title, children, narrow = false }: { title: string | null; children: React.ReactNode; narrow?: boolean }) {
  return (
    <section className={cn("mx-auto flex w-full flex-col gap-6 px-4", narrow ? "max-w-3xl" : "max-w-6xl")}>
      {title && <h2 className="text-center text-2xl font-semibold tracking-tight text-balance md:text-3xl">{title}</h2>}
      {children}
    </section>
  );
}

function OrderButton({ label, className }: { label: string | null; className?: string }) {
  const t = useTranslations("landing");
  return (
    <a href="#order" className={cn(buttonVariants({ size: "lg" }), "h-13 rounded-full px-8 text-base", className)}>
      {label ?? t("orderNow")}
    </a>
  );
}

function Hero({ block, product }: { block: LandingBlock; product: Product | null }) {
  const t = useTranslations();
  const locale = useLocale();
  const image = block.image ?? product?.image ?? null;
  const price = product?.price;
  const compare = product?.compare_price && price && product.compare_price > price ? product.compare_price : null;

  return (
    <section className="mx-auto grid w-full max-w-6xl items-center gap-8 px-4 pt-4 md:grid-cols-2 md:gap-12 md:pt-8">
      <div className="relative aspect-[4/5] overflow-hidden rounded-lg bg-placeholder md:order-2 md:aspect-square">
        {image && <Image src={image} alt={product?.name ?? ""} fill priority sizes="(min-width: 768px) 50vw, 100vw" className="object-cover" />}
      </div>
      <div className="flex flex-col items-start gap-5">
        {block.badge && <span className="rounded-full bg-gold/15 px-3 py-1 text-xs font-bold tracking-wide text-gold">{block.badge}</span>}
        <h1 className="text-3xl leading-tight font-semibold tracking-tight text-balance md:text-5xl">{block.title ?? product?.name}</h1>
        {block.subtitle && <p className="text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground md:text-lg">{block.subtitle}</p>}
        {block.show_price && price !== undefined && (
          <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 tabular-nums">
            {product?.price_from && <span className="text-sm text-muted-foreground">{t("product.from")}</span>}
            <strong className="text-3xl font-semibold text-primary md:text-4xl">{formatTaka(price, locale)}</strong>
            {compare && <s className="text-lg text-muted-foreground">{formatTaka(compare, locale)}</s>}
            {compare && (
              <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-primary">
                {t("product.save", { amount: formatNumber((compare - price) / 100, locale) })}
              </span>
            )}
          </div>
        )}
        {block.ends_at && (
          <div className="flex flex-wrap items-center gap-3 rounded-lg bg-gold/10 px-4 py-3">
            <span className="text-sm font-semibold text-gold">{t("landing.endsIn")}</span>
            <Countdown endsAt={block.ends_at} label={false} />
          </div>
        )}
        <OrderButton label={block.button_label} className="w-full sm:w-auto" />
      </div>
    </section>
  );
}

function Benefits({ block }: { block: LandingBlock }) {
  return (
    <Section title={block.title}>
      <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {block.items.map((item, i) => {
          const Icon = trustIcons[item.icon ?? ""] ?? Star;
          return (
            <li key={i} className="flex items-start gap-4 rounded-lg bg-card p-5">
              <span className="grid size-11 shrink-0 place-items-center rounded-full bg-secondary text-primary">
                <Icon className="size-5" strokeWidth={1.7} />
              </span>
              <span className="flex flex-col gap-1 pt-0.5">
                <span className="font-semibold">{item.title}</span>
                {item.text && <span className="text-sm text-muted-foreground">{item.text}</span>}
              </span>
            </li>
          );
        })}
      </ul>
    </Section>
  );
}

function Gallery({ block }: { block: LandingBlock }) {
  return (
    <Section title={block.title}>
      <div className={cn("grid gap-3", block.images.length === 1 ? "mx-auto w-full max-w-2xl" : "grid-cols-2 md:grid-cols-3")}>
        {block.images.map((src, i) => (
          <div key={src} className={cn("relative overflow-hidden rounded-lg bg-placeholder", block.images.length === 1 ? "aspect-[4/3]" : "aspect-square")}>
            <Image src={src} alt="" fill loading={i < 3 ? "eager" : "lazy"} sizes="(min-width: 768px) 33vw, 50vw" className="object-cover" />
          </div>
        ))}
      </div>
    </Section>
  );
}

function Video({ block }: { block: LandingBlock }) {
  const t = useTranslations("landing");
  if (!block.video_url) return null;

  return (
    <Section title={block.title} narrow>
      <div className="relative aspect-video overflow-hidden rounded-lg bg-foreground">
        <iframe
          src={block.video_url}
          title={block.title ?? t("video")}
          loading="lazy"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
          className="absolute inset-0 size-full border-0"
        />
      </div>
    </Section>
  );
}

function Reviews({ block }: { block: LandingBlock }) {
  const t = useTranslations("landing");
  return (
    <Section title={block.title}>
      <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {block.items.map((item, i) => (
          <li key={i} className="flex flex-col gap-3 rounded-lg bg-card p-5">
            {item.rating && (
              <span className="flex gap-0.5 text-gold" role="img" aria-label={t("rating", { count: item.rating })}>
                {Array.from({ length: item.rating }, (_, n) => (
                  <Star key={n} className="size-4 fill-current" strokeWidth={0} />
                ))}
              </span>
            )}
            {item.text && <p className="text-[15px] leading-relaxed">{item.text}</p>}
            {item.image && (
              <div className="relative aspect-[3/4] overflow-hidden rounded-md bg-placeholder">
                <Image src={item.image} alt="" fill sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 90vw" className="object-cover object-top" />
              </div>
            )}
            <span className="mt-auto text-sm font-semibold">
              {item.title}
              {item.meta && <span className="font-normal text-muted-foreground"> · {item.meta}</span>}
            </span>
          </li>
        ))}
      </ul>
    </Section>
  );
}

function Faq({ block }: { block: LandingBlock }) {
  return (
    <Section title={block.title} narrow>
      <div className="flex flex-col gap-2">
        {block.items.map((item, i) => (
          <details key={i} className="group rounded-lg bg-card [&_summary::-webkit-details-marker]:hidden">
            <summary className="flex cursor-pointer items-center justify-between gap-4 p-5 font-semibold">
              {item.title}
              <span aria-hidden className="text-xl leading-none text-muted-foreground transition-transform group-open:rotate-45">
                +
              </span>
            </summary>
            {item.text && <p className="-mt-1 px-5 pb-5 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground">{item.text}</p>}
          </details>
        ))}
      </div>
    </Section>
  );
}

function Cta({ block }: { block: LandingBlock }) {
  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <div className="flex flex-col items-center gap-4 rounded-lg bg-primary px-6 py-10 text-center text-primary-foreground md:py-14">
        {block.title && <h2 className="text-2xl font-semibold tracking-tight text-balance md:text-3xl">{block.title}</h2>}
        {block.subtitle && <p className="max-w-xl whitespace-pre-line opacity-85">{block.subtitle}</p>}
        <OrderButton label={block.button_label} className="bg-background text-foreground hover:bg-background/90" />
      </div>
    </section>
  );
}

function OrderHeading({ block }: { block: LandingBlock }) {
  const t = useTranslations("landing");
  return (
    <div className="mb-6 flex flex-col items-center gap-2 text-center">
      <h2 className="text-2xl font-semibold tracking-tight text-balance md:text-3xl">{block.title ?? t("orderTitle")}</h2>
      {block.subtitle && <p className="max-w-xl whitespace-pre-line text-muted-foreground">{block.subtitle}</p>}
    </div>
  );
}
