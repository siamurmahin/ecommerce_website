import {
  Anek_Bangla,
  Baloo_Da_2,
  Cormorant_Garamond,
  DM_Sans,
  DM_Serif_Display,
  Fraunces,
  Hind_Siliguri,
  Inter,
  Manrope,
  Noto_Sans_Bengali,
  Noto_Serif_Bengali,
  Playfair_Display,
  Plus_Jakarta_Sans,
  Poppins,
  Tiro_Bangla,
} from "next/font/google";

// Every font admin can pick (BrandSettings::*_FONTS). Only the defaults are preloaded; the
// others ship as @font-face rules and download only if the theme actually uses them.
const fraunces = Fraunces({ variable: "--font-fraunces", subsets: ["latin"] });
const manrope = Manrope({ variable: "--font-manrope", subsets: ["latin"] });
// Headings are only ever semibold; the full variable font is ~190 KB of preload on every page.
const notoSerifBengali = Noto_Serif_Bengali({ variable: "--font-noto-serif-bengali", subsets: ["bengali"], weight: "600" });
const notoSansBengali = Noto_Sans_Bengali({ variable: "--font-noto-sans-bengali", subsets: ["bengali"] });

const playfair = Playfair_Display({ variable: "--font-playfair", subsets: ["latin"], preload: false });
const dmSerif = DM_Serif_Display({ variable: "--font-dm-serif", subsets: ["latin"], weight: "400", preload: false });
const cormorant = Cormorant_Garamond({ variable: "--font-cormorant", subsets: ["latin"], weight: ["500", "600", "700"], preload: false });
const poppins = Poppins({ variable: "--font-poppins", subsets: ["latin"], weight: ["400", "500", "600", "700"], preload: false });
const inter = Inter({ variable: "--font-inter", subsets: ["latin"], preload: false });
const dmSans = DM_Sans({ variable: "--font-dm-sans", subsets: ["latin"], preload: false });
const plusJakarta = Plus_Jakarta_Sans({ variable: "--font-plus-jakarta", subsets: ["latin"], preload: false });
const tiroBangla = Tiro_Bangla({ variable: "--font-tiro-bangla", subsets: ["bengali"], weight: "400", preload: false });
const hindSiliguri = Hind_Siliguri({ variable: "--font-hind-siliguri", subsets: ["bengali"], weight: ["400", "500", "600", "700"], preload: false });
const anekBangla = Anek_Bangla({ variable: "--font-anek-bangla", subsets: ["bengali"], preload: false });
const balooDa = Baloo_Da_2({ variable: "--font-baloo-da-2", subsets: ["bengali"], preload: false });

const all = [fraunces, manrope, notoSerifBengali, notoSansBengali, playfair, dmSerif, cormorant, poppins, inter, dmSans, plusJakarta, tiroBangla, hindSiliguri, anekBangla, balooDa];

/** Class names that define every `--font-*` variable; goes on <html>. */
export const fontVariables = all.map((f) => f.variable).join(" ");

const css = {
  fraunces: "--font-fraunces",
  manrope: "--font-manrope",
  playfair: "--font-playfair",
  "dm-serif": "--font-dm-serif",
  cormorant: "--font-cormorant",
  poppins: "--font-poppins",
  inter: "--font-inter",
  "dm-sans": "--font-dm-sans",
  "plus-jakarta": "--font-plus-jakarta",
  "noto-serif-bengali": "--font-noto-serif-bengali",
  "noto-sans-bengali": "--font-noto-sans-bengali",
  "tiro-bangla": "--font-tiro-bangla",
  "hind-siliguri": "--font-hind-siliguri",
  "anek-bangla": "--font-anek-bangla",
  "baloo-da-2": "--font-baloo-da-2",
} as const;

/** `var(--font-x)` for a font key from the API, or the fallback for an unknown key. */
export function fontVar(key: string, fallback: keyof typeof css): string {
  return `var(${css[key as keyof typeof css] ?? css[fallback]})`;
}
