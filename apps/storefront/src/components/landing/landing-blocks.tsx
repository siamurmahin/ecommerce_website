import { ArrowDown, Banknote, Check, ChevronDown, Phone, Quote, Star, TriangleAlert } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { Countdown } from "@/components/commerce/countdown";
import { trustIcons } from "@/components/home/trust-strip";
import { buttonVariants } from "@/components/ui/button";
import type { CheckoutOptions, LandingBlock, Product } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { LandingOrder } from "./landing-order";
import { OfferCountdown } from "./offer-countdown";
import type { Look } from "./templates";

type Props = {
  block: LandingBlock;
  product: Product | null;
  options: CheckoutOptions;
  landingPageId: number;
  look: Look;
  /** Sits on a white band (story design), so cards switch to the page colour to stay visible. */
  onBand?: boolean;
  /** Shop hotline from settings, for the call block. */
  hotline?: { label: string; tel: string } | null;
};

/** White card with a hairline ring and a soft drop shadow; the page colour instead when it sits on a white band. */
const surface = (onBand: boolean) =>
  cn("rounded-2xl ring-1 ring-border/70 shadow-[0_1px_2px_rgba(23,23,23,0.04),0_12px_32px_-18px_rgba(23,23,23,0.18)]", onBand ? "bg-background" : "bg-card");

/** Soft colour glow behind a panel; purely decorative. */
function Glow({ className }: { className?: string }) {
  return <span aria-hidden className={cn("pointer-events-none absolute -z-10 rounded-full blur-3xl", className)} />;
}

/** Glows behind an open (unboxed) section, faded out towards the bottom so they never end in a hard edge. */
function GlowLayer({ children }: { children: React.ReactNode }) {
  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 overflow-hidden [mask-image:linear-gradient(to_bottom,black_55%,transparent)]">
      {children}
    </div>
  );
}

/** One admin-built block, drawn in the page's design. Every button points at the order form (#order). */
export function LandingBlockView({ block, product, options, landingPageId, look, onBand = false, hotline = null }: Props) {
  const card = surface(onBand);
  const cod = options.payment_methods.some((m) => m.code === "cod");

  switch (block.type) {
    case "announcement":
      return null; // drawn above the page header
    case "hero":
      return <Hero block={block} product={product} look={look} cod={cod} />;
    case "trust":
      return <Trust block={block} />;
    case "offer":
      return <Offer block={block} product={product} look={look} />;
    case "notice":
      return <Notice block={block} card={card} />;
    case "checklist":
      return <Checklist block={block} look={look} card={card} />;
    case "benefits":
      return <Benefits block={block} look={look} card={card} />;
    case "features":
      return <Features block={block} look={look} card={card} />;
    case "gallery":
      return <Gallery block={block} look={look} />;
    case "video":
      return <Video block={block} look={look} />;
    case "text":
      return (
        <Section block={block} look={look} narrow>
          {block.content && (
            <div
              className={cn("prose-content text-[15px] leading-relaxed md:text-base", look.items === "lines" ? "text-center md:text-lg" : cn("p-6 md:p-10", card))}
              dangerouslySetInnerHTML={{ __html: block.content }}
            />
          )}
        </Section>
      );
    case "reviews":
      return <Reviews block={block} look={look} card={card} />;
    case "faq":
      return <Faq block={block} look={look} card={card} />;
    case "cta":
      return <Cta block={block} look={look} />;
    case "call":
      return hotline ? <Call block={block} hotline={hotline} /> : null;
    case "order_form":
      return (
        <section id="order" className="mx-auto w-full max-w-6xl scroll-mt-6 px-4">
          <div className="mb-8">
            <Heading block={block} look={look} fallback={null} />
          </div>
          <LandingOrder product={product} options={options} landingPageId={landingPageId} packages={block.packages} />
        </section>
      );
  }
}

function Section({ block, look, children, narrow = false }: { block: LandingBlock; look: Look; children: React.ReactNode; narrow?: boolean }) {
  return (
    <section className={cn("mx-auto flex w-full flex-col gap-8 px-4 md:gap-10", narrow ? "max-w-3xl" : "max-w-6xl")}>
      <Heading block={block} look={look} />
      {children}
    </section>
  );
}

/** Section heading: small label above, the title, an optional line of text below, and a gold rule in the elegant design. */
function Heading({ block, look, fallback = null }: { block: LandingBlock; look: Look; fallback?: string | null }) {
  const t = useTranslations("landing");
  const title = block.title ?? (block.type === "order_form" ? t("orderTitle") : fallback);
  const subtitle = block.type === "hero" ? null : block.subtitle;
  if (!title && !block.badge) return null;

  return (
    <div className="mx-auto flex max-w-2xl flex-col items-center gap-3 text-center">
      {block.badge && (
        <span className="inline-flex items-center gap-2 rounded-full bg-primary/8 px-3 py-1 text-xs font-semibold text-primary">
          <span aria-hidden className="size-1.5 rounded-full bg-primary" />
          {block.badge}
        </span>
      )}
      {title && <h2 className="text-[1.75rem] leading-[1.2] font-semibold tracking-tight text-balance md:text-[2.5rem]">{title}</h2>}
      {title && look.accent && <span aria-hidden className="h-0.5 w-12 rounded-full bg-gold" />}
      {subtitle && <p className="text-[15px] leading-relaxed text-pretty whitespace-pre-line text-muted-foreground md:text-base">{subtitle}</p>}
    </div>
  );
}

function OrderButton({ label, className, inverse = false }: { label: string | null; className?: string; inverse?: boolean }) {
  const t = useTranslations("landing");
  return (
    <a
      href="#order"
      className={cn(
        buttonVariants({ size: "lg" }),
        "group h-14 gap-2.5 rounded-full px-8 text-base font-semibold",
        inverse ? "bg-background text-foreground shadow-[0_14px_36px_-14px_rgba(0,0,0,0.5)] hover:bg-background/90" : "shadow-[0_14px_36px_-14px] shadow-primary/70",
        className,
      )}
    >
      {label ?? t("orderNow")}
      <ArrowDown aria-hidden className="size-4 transition-transform group-hover:translate-y-0.5 motion-reduce:transition-none" strokeWidth={2.2} />
    </a>
  );
}

function Stars({ rating, className }: { rating: number; className?: string }) {
  const t = useTranslations("landing");
  return (
    <span className={cn("flex gap-0.5 text-gold", className)} role="img" aria-label={t("rating", { count: rating })}>
      {Array.from({ length: 5 }, (_, n) => (
        <Star key={n} className={cn("size-4", n < rating ? "fill-current" : "opacity-25")} strokeWidth={0} fill="currentColor" />
      ))}
    </span>
  );
}

function Hero({ block, product, look, cod }: { block: LandingBlock; product: Product | null; look: Look; cod: boolean }) {
  const t = useTranslations();
  const locale = useLocale();
  const image = block.image ?? product?.image ?? null;
  const price = product?.price;
  const compare = product?.compare_price && price && product.compare_price > price ? product.compare_price : null;
  const percent = compare && price ? Math.round(((compare - price) / compare) * 100) : null;
  const rating = product && product.reviews_count > 0 ? product : null;
  const band = look.hero === "band";
  const banner = look.hero === "banner";
  const centered = look.hero === "centered" || banner;
  const compact = look.hero === "compact";

  const badge = block.badge && (
    <span
      className={cn(
        "inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold tracking-wide",
        band ? "bg-primary-foreground/12 text-primary-foreground ring-1 ring-primary-foreground/20" : "bg-gold/12 text-[#8a6d37]",
      )}
    >
      <span aria-hidden className={cn("size-1.5 rounded-full", band ? "bg-gold" : "bg-gold")} />
      {block.badge}
    </span>
  );

  const priceRow = block.show_price && price !== undefined && (
    <div className={cn("flex flex-wrap items-center gap-x-3 gap-y-2 tabular-nums", centered && "justify-center")}>
      {product?.price_from && <span className={cn("text-sm", band ? "text-primary-foreground/75" : "text-muted-foreground")}>{t("product.from")}</span>}
      <strong className={cn("font-heading font-semibold", band ? "text-5xl text-primary-foreground md:text-6xl" : "text-4xl text-primary md:text-5xl")}>{formatTaka(price, locale)}</strong>
      {compare && <s className={cn("text-lg", band ? "text-primary-foreground/55" : "text-muted-foreground")}>{formatTaka(compare, locale)}</s>}
      {compare && (
        <span className={cn("rounded-full px-2.5 py-1 text-xs font-bold", band ? "bg-gold text-white" : "bg-primary/8 text-primary")}>
          {t("product.save", { amount: formatNumber((compare - price) / 100, locale) })}
        </span>
      )}
    </div>
  );

  const countdown = block.ends_at && (
    <div
      className={cn(
        "flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3",
        band ? "bg-primary-foreground/8 ring-1 ring-primary-foreground/15" : "bg-card ring-1 ring-border/70",
        centered && "justify-center",
      )}
    >
      <span className={cn("text-sm font-semibold", band ? "text-primary-foreground" : "text-foreground")}>{t("landing.endsIn")}</span>
      <Countdown endsAt={block.ends_at} label={false} inverse={band} className={band ? "text-primary-foreground" : undefined} />
    </div>
  );

  const title = block.title ?? product?.name;
  const subtitle = block.subtitle && (
    <p className={cn("max-w-xl text-base leading-relaxed text-pretty whitespace-pre-line md:text-lg", band ? "text-primary-foreground/80" : "text-muted-foreground", centered && "mx-auto")}>
      {block.subtitle}
    </p>
  );

  // Facts that are true for this shop and product, floated over the image.
  const chips = (
    <>
      {percent && percent >= 5 && (
        <span className="absolute top-4 left-4 rounded-full bg-gold px-3 py-1.5 text-sm font-bold text-white shadow-lg tabular-nums">
          −{formatNumber(percent, locale)}%
        </span>
      )}
      {(cod || rating) && (
        <span className="absolute right-4 bottom-4 left-4 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl bg-card/90 px-4 py-3 text-sm shadow-lg ring-1 ring-black/5 backdrop-blur-md sm:right-auto">
          {rating && (
            <span className="flex items-center gap-1.5 font-semibold tabular-nums">
              <Star className="size-4 fill-gold text-gold" strokeWidth={0} />
              {formatNumber(Math.round(rating.rating_avg * 10) / 10, locale)}
              <span className="font-normal text-muted-foreground">({t("reviews.count", { count: formatNumber(rating.reviews_count, locale) })})</span>
            </span>
          )}
          {cod && (
            <span className="flex items-center gap-2 font-semibold">
              <Banknote className="size-4 text-primary" strokeWidth={1.8} />
              {t("landing.cod")}
            </span>
          )}
        </span>
      )}
    </>
  );

  const picture = (className: string, sizes: string, withChips = true) => (
    <div className={cn("relative overflow-hidden bg-placeholder", className)}>
      {image && <Image src={image} alt={product?.name ?? ""} fill priority sizes={sizes} className="object-cover" />}
      {withChips && chips}
    </div>
  );

  const heading = (size: string) => <h1 className={cn("leading-[1.08] font-semibold tracking-tight text-balance", size)}>{title}</h1>;

  // Headline in a framed card over a wide banner image; the offer block below carries the price.
  if (banner) {
    return (
      <section className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 pt-4 md:gap-5 md:pt-8">
        <div className="flex flex-col items-center gap-4 rounded-[1.75rem] bg-card px-5 py-8 text-center ring-1 ring-primary/15 md:px-14 md:py-12">
          {badge}
          {heading("text-[2rem] md:text-[3.25rem]")}
          {subtitle}
          {priceRow}
          {countdown}
        </div>
        {picture("aspect-[4/3] rounded-[1.75rem] md:aspect-[16/9]", "(min-width: 1152px) 1120px, 100vw")}
      </section>
    );
  }

  if (centered) {
    return (
      <section className="relative isolate">
        <GlowLayer>
          <Glow className="top-0 left-1/2 size-[36rem] -translate-x-1/2 bg-primary/8" />
        </GlowLayer>
        <div className="mx-auto flex w-full max-w-6xl flex-col items-center gap-10 px-4 pt-10 md:gap-16 md:pt-20">
          <div className="flex max-w-3xl flex-col items-center gap-6 text-center">
            {badge}
            {heading("text-[2.5rem] md:text-7xl")}
            {subtitle}
            {priceRow}
            {countdown}
            <OrderButton label={block.button_label} className="w-full sm:w-auto" />
          </div>
          {picture("aspect-[4/5] w-full max-w-5xl rounded-[1.75rem] shadow-[0_40px_80px_-40px_rgba(15,76,58,0.45)] sm:aspect-[16/10]", "(min-width: 1024px) 1024px, 100vw")}
        </div>
      </section>
    );
  }

  if (band) {
    return (
      <section className="mx-auto w-full max-w-6xl px-4 pt-4 md:pt-8">
        <div className="relative isolate grid items-center gap-8 overflow-hidden rounded-[1.75rem] bg-primary p-5 text-primary-foreground sm:p-8 md:grid-cols-2 md:gap-14 md:p-14">
          <Glow className="-top-32 -right-24 size-96 bg-gold/30" />
          <Glow className="-bottom-40 -left-24 size-96 bg-white/10" />
          {picture("aspect-square rounded-2xl ring-1 ring-white/15 md:order-2", "(min-width: 768px) 50vw, 100vw")}
          <div className="flex flex-col items-start gap-6">
            {badge}
            {heading("text-[2rem] md:text-[3.25rem]")}
            {subtitle}
            {priceRow}
            {countdown}
            <OrderButton label={block.button_label} inverse className="w-full sm:w-auto" />
          </div>
        </div>
      </section>
    );
  }

  // Short hero in one card, so the order form (next block) is on the first screen.
  if (compact) {
    return (
      <section className="mx-auto w-full max-w-6xl px-4 pt-4 md:pt-8">
        <div className="grid items-center gap-5 rounded-[1.75rem] bg-card p-4 ring-1 ring-border/70 sm:grid-cols-[180px_1fr] sm:p-5 md:grid-cols-[260px_1fr] md:gap-8">
          {picture("aspect-[4/3] rounded-2xl sm:aspect-square", "(min-width: 768px) 260px, (min-width: 640px) 180px, 100vw", false)}
          <div className="flex flex-col items-start gap-3 sm:pr-4">
            {badge}
            {heading("text-[1.75rem] md:text-[2.5rem]")}
            {subtitle}
            {priceRow}
            {countdown}
          </div>
        </div>
      </section>
    );
  }

  // split (image right) or split-reverse (image left)
  return (
    <section className="relative isolate">
      <GlowLayer>
        <Glow className={cn("-top-24 size-[30rem] bg-primary/10", look.hero === "split" ? "right-0" : "left-0")} />
        <Glow className={cn("top-1/3 size-80 bg-gold/15", look.hero === "split" ? "left-1/4" : "right-1/4")} />
      </GlowLayer>
      <div className="mx-auto grid w-full max-w-6xl items-center gap-8 px-4 pt-4 pb-2 md:grid-cols-2 md:gap-14 md:pt-10">
        {picture(
          cn("aspect-[4/5] rounded-[1.75rem] shadow-[0_40px_80px_-40px_rgba(15,76,58,0.45)] md:aspect-square", look.hero === "split" && "md:order-2"),
          "(min-width: 768px) 50vw, 100vw",
        )}
        <div className="flex flex-col items-start gap-6">
          {badge}
          {heading("text-[2.25rem] md:text-6xl")}
          {subtitle}
          {priceRow}
          {countdown}
          <OrderButton label={block.button_label} className="w-full sm:w-auto" />
        </div>
      </div>
    </section>
  );
}

/** Two columns for 2 or 4 items so the grid stays even, otherwise three. */
const columns = (count: number) => (count === 2 || count === 4 ? "lg:grid-cols-2" : "lg:grid-cols-3");

function Trust({ block }: { block: LandingBlock }) {
  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <ul className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-center sm:gap-3">
        {block.items.map((item, i) => {
          const Icon = trustIcons[item.icon ?? ""] ?? Check;
          return (
            <li key={i} className="flex items-center gap-2.5 rounded-full bg-card py-2 pr-4 pl-2 text-sm font-semibold ring-1 ring-border/70">
              <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary/8 text-primary">
                <Icon className="size-4" strokeWidth={1.8} />
              </span>
              {item.title}
            </li>
          );
        })}
      </ul>
    </section>
  );
}

function Offer({ block, product, look }: { block: LandingBlock; product: Product | null; look: Look }) {
  const t = useTranslations();
  const locale = useLocale();
  if (!product) return null;

  const price = product.price;
  const compare = product.compare_price && product.compare_price > price ? product.compare_price : null;
  const percent = compare ? Math.round(((compare - price) / compare) * 100) : null;
  const soft = look.offer === "soft";
  const discount = percent ? t("landing.discount", { percent: formatNumber(percent, locale) }) : null;
  const label = [block.badge, discount].filter(Boolean).join(" · ");

  const body = (
    <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
      {label && <span className="rounded-full bg-gold px-3.5 py-1.5 text-xs font-bold text-white shadow-sm">{label}</span>}
      {block.title && <h2 className="text-[1.75rem] leading-[1.2] font-semibold tracking-tight text-balance md:text-[2.75rem]">{block.title}</h2>}
      {block.ends_at && <OfferCountdown endsAt={block.ends_at} inverse={!soft} />}
      <div className="flex flex-col items-center gap-2 tabular-nums">
        {compare && (
          <span className={cn("text-base", soft ? "text-muted-foreground" : "text-primary-foreground/70")}>
            {t("landing.regularPrice")} <s>{formatTaka(compare, locale)}</s>
          </span>
        )}
        <span className="flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1">
          <span className={cn("text-lg font-semibold md:text-xl", soft ? "text-foreground" : "text-primary-foreground/90")}>{t("landing.offerPrice")}</span>
          {product.price_from && <span className="text-sm opacity-75">{t("product.from")}</span>}
          <strong className={cn("font-heading text-6xl leading-none font-semibold md:text-8xl", soft ? "text-primary" : "text-primary-foreground")}>{formatTaka(price, locale)}</strong>
        </span>
        {compare && (
          <span className={cn("mt-1 rounded-full px-3 py-1 text-sm font-semibold", soft ? "bg-primary/8 text-primary" : "bg-primary-foreground/10 text-primary-foreground")}>
            {t("product.save", { amount: formatNumber((compare - price) / 100, locale) })}
          </span>
        )}
      </div>
      <OrderButton label={block.button_label} inverse={!soft} className="w-full sm:w-auto sm:min-w-72" />
    </div>
  );

  const glows = soft ? (
    <>
      <Glow className="-top-24 left-1/4 size-80 bg-primary/10" />
      <Glow className="-bottom-24 right-1/4 size-80 bg-gold/15" />
    </>
  ) : (
    <>
      <Glow className="-top-32 left-1/2 size-[32rem] -translate-x-1/2 bg-gold/25" />
      <Glow className="-bottom-40 -left-20 size-96 bg-white/10" />
    </>
  );

  if (look.offer === "panel") {
    return (
      <section className="mx-auto w-full max-w-6xl px-4">
        <div className="relative isolate overflow-hidden rounded-[1.75rem] bg-primary px-5 py-12 text-primary-foreground md:px-12 md:py-16">
          {glows}
          {body}
        </div>
      </section>
    );
  }

  return (
    <section className={cn("relative isolate w-full overflow-hidden px-4 py-14 md:py-20", soft ? "bg-secondary" : "bg-primary text-primary-foreground")}>
      {glows}
      {body}
    </section>
  );
}

function Notice({ block, card }: { block: LandingBlock; card: string }) {
  const t = useTranslations("landing");
  const locale = useLocale();

  return (
    <section className="mx-auto w-full max-w-3xl px-4">
      <div className={cn("flex flex-col gap-5 p-6 sm:flex-row sm:gap-6 md:p-8", card)}>
        <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-gold/12 text-[#8a6d37]">
          <TriangleAlert className="size-6" strokeWidth={1.8} />
        </span>
        <div className="flex flex-col gap-4">
          <h2 className="text-xl font-semibold tracking-tight md:text-2xl">{block.title ?? t("notice")}</h2>
          <ol className="flex flex-col gap-3 text-[15px] leading-relaxed">
            {block.items.map((item, i) => (
              <li key={i} className="flex gap-3">
                <span className="grid size-6 shrink-0 place-items-center rounded-full bg-primary/8 text-xs font-bold text-primary tabular-nums">{formatNumber(i + 1, locale)}</span>
                {item.title}
              </li>
            ))}
          </ol>
        </div>
      </div>
    </section>
  );
}

function Checklist({ block, look, card }: { block: LandingBlock; look: Look; card: string }) {
  return (
    <Section block={block} look={look} narrow>
      <ul className="flex flex-col gap-3">
        {block.items.map((item, i) => (
          <li key={i} className={cn("flex items-center gap-4 p-3 pr-5 md:gap-5", card)}>
            {item.image ? (
              <span className="relative size-18 shrink-0 overflow-hidden rounded-xl bg-placeholder md:size-22">
                <Image src={item.image} alt="" fill sizes="88px" className="object-cover" />
              </span>
            ) : (
              <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-primary/8 text-primary">
                <Check className="size-5" strokeWidth={2.2} />
              </span>
            )}
            <span className="flex flex-1 flex-col gap-1">
              <span className="font-semibold md:text-lg">{item.title}</span>
              {item.text && <span className="text-sm leading-relaxed text-muted-foreground">{item.text}</span>}
            </span>
            {item.image && (
              <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                <Check className="size-4" strokeWidth={2.6} />
              </span>
            )}
          </li>
        ))}
      </ul>
    </Section>
  );
}

function Benefits({ block, look, card }: { block: LandingBlock; look: Look; card: string }) {
  const lines = look.items === "lines";

  return (
    <Section block={block} look={look}>
      <ul className={cn("grid sm:grid-cols-2", columns(block.items.length), lines ? "gap-x-12" : "gap-4")}>
        {block.items.map((item, i) => {
          const Icon = trustIcons[item.icon ?? ""] ?? Star;
          return (
            <li
              key={i}
              className={cn(
                "flex gap-4",
                lines ? "items-start border-t border-border py-6" : cn("flex-col p-6 transition-transform duration-300 hover:-translate-y-1 motion-reduce:transition-none md:p-7", card),
              )}
            >
              <span
                className={cn(
                  "grid shrink-0 place-items-center",
                  lines ? "size-9 text-primary" : "size-12 rounded-2xl",
                  !lines && (look.solidIcons ? "bg-primary text-primary-foreground shadow-[0_10px_24px_-12px] shadow-primary" : "bg-primary/8 text-primary"),
                )}
              >
                <Icon className="size-5.5" strokeWidth={1.7} />
              </span>
              <span className="flex flex-col gap-1.5">
                <span className="text-lg font-semibold">{item.title}</span>
                {item.text && <span className="text-[15px] leading-relaxed text-muted-foreground">{item.text}</span>}
              </span>
            </li>
          );
        })}
      </ul>
    </Section>
  );
}

function Features({ block, look, card }: { block: LandingBlock; look: Look; card: string }) {
  const locale = useLocale();

  return (
    <Section block={block} look={look}>
      <ul className={cn("grid gap-4 sm:grid-cols-2", columns(block.items.length))}>
        {block.items.map((item, i) => (
          <li key={i} className={cn("relative flex flex-col gap-5 overflow-hidden p-7", card)}>
            <span aria-hidden className="absolute -top-3 right-4 font-heading text-[5.5rem] leading-none font-semibold text-primary/6 tabular-nums">
              {formatNumber(i + 1, locale).padStart(2, locale === "bn" ? "০" : "0")}
            </span>
            <span className="grid size-10 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground tabular-nums">{formatNumber(i + 1, locale)}</span>
            <h3 className="text-xl font-semibold tracking-tight">{item.title}</h3>
            {item.text && (
              <ul className="flex flex-col gap-3 text-[15px] text-muted-foreground">
                {item.text
                  .split("\n")
                  .filter((line) => line.trim() !== "")
                  .map((line, n) => (
                    <li key={n} className="flex gap-3">
                      <span aria-hidden className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-primary/8 text-primary">
                        <Check className="size-3" strokeWidth={3} />
                      </span>
                      {line}
                    </li>
                  ))}
              </ul>
            )}
          </li>
        ))}
      </ul>
    </Section>
  );
}

function Call({ block, hotline }: { block: LandingBlock; hotline: { label: string; tel: string } }) {
  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <div className="relative isolate flex flex-col items-center gap-8 overflow-hidden rounded-[1.75rem] bg-primary px-6 py-10 text-center text-primary-foreground md:flex-row md:justify-between md:px-14 md:py-14 md:text-left">
        <Glow className="-top-24 -right-16 size-80 bg-gold/25" />
        <div className="flex flex-col items-center gap-3 md:items-start">
          {block.badge && <span className="rounded-full bg-primary-foreground/12 px-3 py-1 text-xs font-semibold ring-1 ring-primary-foreground/20">{block.badge}</span>}
          {block.title && <h2 className="max-w-xl text-[1.75rem] leading-[1.2] font-semibold tracking-tight text-balance md:text-4xl">{block.title}</h2>}
          {block.subtitle && <p className="max-w-xl whitespace-pre-line opacity-80">{block.subtitle}</p>}
        </div>
        <a
          href={`tel:${hotline.tel}`}
          className="group flex shrink-0 items-center gap-4 rounded-full bg-background py-2.5 pr-7 pl-2.5 text-foreground shadow-[0_14px_36px_-14px_rgba(0,0,0,0.5)] transition-transform hover:-translate-y-0.5 motion-reduce:transition-none"
        >
          <span className="grid size-12 place-items-center rounded-full bg-primary text-primary-foreground">
            <Phone className="size-5" strokeWidth={1.8} />
          </span>
          <span className="text-xl font-semibold tabular-nums md:text-2xl">{hotline.label}</span>
        </a>
      </div>
    </section>
  );
}

function Gallery({ block, look }: { block: LandingBlock; look: Look }) {
  const count = block.images.length;
  const single = count === 1;
  // One large lead image when the rest fill the grid exactly: 3 (lead + 2) or 5, 9… (lead + 4n).
  const lead = count === 3 || (count >= 5 && count % 4 === 1);

  return (
    <Section block={block} look={look}>
      <div className={cn("grid gap-3 md:gap-4", single ? "mx-auto w-full max-w-3xl" : lead && count === 3 ? "grid-cols-2 md:grid-cols-3" : lead ? "grid-cols-2 md:grid-cols-4" : "grid-cols-2 md:grid-cols-3")}>
        {block.images.map((src, i) => (
          <div
            key={`${i}-${src}`}
            className={cn(
              "group relative overflow-hidden rounded-2xl bg-placeholder",
              single ? "aspect-[4/3]" : "aspect-square",
              lead && i === 0 && "col-span-2 row-span-2 md:aspect-auto",
            )}
          >
            <Image
              src={src}
              alt=""
              fill
              loading={i < 3 ? "eager" : "lazy"}
              sizes={lead && i === 0 ? "(min-width: 768px) 50vw, 100vw" : "(min-width: 768px) 33vw, 50vw"}
              className="object-cover transition-transform duration-500 group-hover:scale-[1.04] motion-reduce:transition-none"
            />
          </div>
        ))}
      </div>
    </Section>
  );
}

function Video({ block, look }: { block: LandingBlock; look: Look }) {
  const t = useTranslations("landing");
  if (!block.video_url) return null;

  return (
    <Section block={block} look={look}>
      <div className="relative mx-auto aspect-video w-full max-w-4xl overflow-hidden rounded-[1.75rem] bg-foreground shadow-[0_40px_80px_-40px_rgba(23,23,23,0.5)] ring-1 ring-border/70">
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

function Reviews({ block, look, card }: { block: LandingBlock; look: Look; card: string }) {
  const lines = look.items === "lines";

  return (
    <Section block={block} look={look}>
      <ul className={cn(lines ? "grid gap-x-12 sm:grid-cols-2 lg:grid-cols-3" : "columns-1 gap-4 sm:columns-2 lg:columns-3")}>
        {block.items.map((item, i) => (
          <li key={i} className={cn("flex flex-col gap-4", lines ? "border-t border-border py-6" : cn("mb-4 break-inside-avoid p-6", card))}>
            {item.image && (
              <div className="relative aspect-[3/4] overflow-hidden rounded-xl bg-placeholder">
                <Image src={item.image} alt="" fill sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 90vw" className="object-cover object-top" />
              </div>
            )}
            <div className="flex items-center justify-between gap-3">
              {item.rating ? <Stars rating={item.rating} /> : <span />}
              {!lines && <Quote aria-hidden className="size-7 fill-primary/8 text-primary/15" strokeWidth={1.5} />}
            </div>
            {item.text && <p className={cn("leading-relaxed text-pretty", lines ? "font-heading text-lg" : "text-[15px]")}>{item.text}</p>}
            <span className="mt-auto flex items-center gap-3">
              <span aria-hidden className="grid size-10 shrink-0 place-items-center rounded-full bg-primary/8 font-heading font-semibold text-primary">
                {item.title.trim().charAt(0)}
              </span>
              <span className="flex flex-col text-sm">
                <span className="font-semibold">{item.title}</span>
                {item.meta && <span className="text-muted-foreground">{item.meta}</span>}
              </span>
            </span>
          </li>
        ))}
      </ul>
    </Section>
  );
}

function Faq({ block, look, card }: { block: LandingBlock; look: Look; card: string }) {
  const lines = look.items === "lines";

  return (
    <Section block={block} look={look} narrow>
      <div className={cn("flex flex-col", lines ? "border-t border-border" : "gap-3")}>
        {block.items.map((item, i) => (
          <details
            key={i}
            className={cn("group [&_summary::-webkit-details-marker]:hidden", lines ? "border-b border-border" : cn("transition-shadow open:ring-primary/25", card))}
          >
            <summary className={cn("flex cursor-pointer items-center justify-between gap-4 font-semibold md:text-lg", lines ? "py-5" : "p-5 md:px-6")}>
              {item.title}
              <span aria-hidden className="grid size-8 shrink-0 place-items-center rounded-full bg-primary/8 text-primary transition-transform group-open:rotate-180 motion-reduce:transition-none">
                <ChevronDown className="size-4" strokeWidth={2.2} />
              </span>
            </summary>
            {item.text && <p className={cn("-mt-1 pb-5 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground", !lines && "px-5 md:px-6")}>{item.text}</p>}
          </details>
        ))}
      </div>
    </Section>
  );
}

function Cta({ block, look }: { block: LandingBlock; look: Look }) {
  // Quiet bordered card in the minimal design; an emerald panel everywhere else.
  const loud = look.items === "cards";

  // Just a button between sections when there's nothing to say around it.
  if (!block.title && !block.subtitle) {
    return (
      <div className="flex justify-center px-4">
        <OrderButton label={block.button_label} />
      </div>
    );
  }

  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <div
        className={cn(
          "relative isolate flex flex-col items-center gap-5 overflow-hidden rounded-[1.75rem] px-6 py-12 text-center md:py-16",
          loud ? "bg-primary text-primary-foreground" : "bg-card ring-1 ring-border/70",
        )}
      >
        {loud && (
          <>
            <Glow className="-top-28 -left-16 size-80 bg-gold/25" />
            <Glow className="-right-20 -bottom-32 size-96 bg-white/10" />
          </>
        )}
        {block.title && <h2 className="max-w-2xl text-[1.75rem] leading-[1.2] font-semibold tracking-tight text-balance md:text-[2.75rem]">{block.title}</h2>}
        {block.subtitle && <p className={cn("max-w-xl whitespace-pre-line md:text-lg", loud ? "opacity-80" : "text-muted-foreground")}>{block.subtitle}</p>}
        <OrderButton label={block.button_label} inverse={loud} className="mt-2" />
      </div>
    </section>
  );
}
