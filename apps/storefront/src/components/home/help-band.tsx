import { MessageCircle, Phone } from "lucide-react";
import { useTranslations } from "next-intl";
import { Reveal } from "@/components/motion/reveal";
import type { SiteSettings } from "@/lib/api/types";

/** Phone/WhatsApp ordering is how many Bangladeshi customers prefer to buy. */
export function HelpBand({ title, note, settings }: { title: string | null; note: string | null; settings: Pick<SiteSettings, "hotline" | "hotline_tel" | "whatsapp_url"> }) {
  const t = useTranslations("home");
  const { hotline, hotline_tel: hotlineTel, whatsapp_url: whatsappUrl } = settings;

  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <Reveal className="flex flex-col gap-5 rounded-lg bg-card p-6 md:flex-row md:items-center md:justify-between md:p-10">
        <div className="flex max-w-xl flex-col gap-2">
          <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">{title ?? t("helpTitle")}</h2>
          {note && <p className="text-muted-foreground">{note}</p>}
        </div>
        <div className="flex flex-wrap gap-3">
          {hotline && (
            <a href={`tel:${hotlineTel}`} className="inline-flex h-12 items-center gap-2 rounded-full bg-primary px-6 text-[15px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">
              <Phone className="size-4" strokeWidth={2} />
              {t("callUs")} · <span className="tabular-nums">{hotline}</span>
            </a>
          )}
          {whatsappUrl && (
            <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="inline-flex h-12 items-center gap-2 rounded-full border border-border px-6 text-[15px] font-semibold transition-colors hover:border-primary hover:text-primary">
              <MessageCircle className="size-4" strokeWidth={2} />
              {t("whatsappUs")}
            </a>
          )}
        </div>
      </Reveal>
    </section>
  );
}
