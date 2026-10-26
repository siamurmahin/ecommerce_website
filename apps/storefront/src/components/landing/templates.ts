import type { LandingPage } from "@/lib/api/types";

export type LandingTemplate = LandingPage["template"];

/**
 * How each ready-made design (backend `LandingTemplates`) looks. Blocks read these instead of
 * branching on the template name, so a new design is one more entry here.
 */
export type Look = {
  hero: "split" | "band" | "centered" | "split-reverse" | "compact" | "banner";
  /** Item cards (benefits, reviews, FAQ): white cards, or plain rows split by hairlines. */
  items: "cards" | "lines";
  /** Every other block sits on a full-width white band. */
  bands: boolean;
  /** Benefit icons on a solid emerald circle instead of a soft one. */
  solidIcons: boolean;
  /** Offer price block: emerald card in the column, full-width emerald band, or full-width soft tint. */
  offer: "panel" | "band" | "soft";
  /** Short gold rule under section headings. */
  accent: boolean;
  /** Vertical rhythm between blocks. */
  gap: string;
};

export const looks: Record<LandingTemplate, Look> = {
  classic: { hero: "split", items: "cards", bands: false, solidIcons: false, offer: "panel", accent: false, gap: "gap-14 md:gap-20" },
  offer: { hero: "band", items: "cards", bands: false, solidIcons: true, offer: "panel", accent: false, gap: "gap-12 md:gap-16" },
  minimal: { hero: "centered", items: "lines", bands: false, solidIcons: false, offer: "soft", accent: false, gap: "gap-16 md:gap-28" },
  story: { hero: "split-reverse", items: "cards", bands: true, solidIcons: false, offer: "panel", accent: false, gap: "gap-0" },
  quick: { hero: "compact", items: "cards", bands: false, solidIcons: false, offer: "panel", accent: false, gap: "gap-10 md:gap-14" },
  bundle: { hero: "banner", items: "cards", bands: false, solidIcons: false, offer: "panel", accent: false, gap: "gap-10 md:gap-14" },
  showcase: { hero: "split", items: "cards", bands: false, solidIcons: false, offer: "band", accent: false, gap: "gap-14 md:gap-20" },
  elegant: { hero: "split-reverse", items: "cards", bands: false, solidIcons: false, offer: "soft", accent: true, gap: "gap-14 md:gap-24" },
};

export const lookFor = (template: string | null | undefined): Look => looks[(template ?? "classic") as LandingTemplate] ?? looks.classic;
