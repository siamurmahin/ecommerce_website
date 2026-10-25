import type { SiteSettings } from "@/lib/api/types";
import { fontVar } from "@/lib/fonts";

const HEX = /^#[0-9a-f]{6}$/i;

/** White or near-black, whichever reads better on `hex` (WCAG relative luminance). */
function onColor(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.4 ? "#171717" : "#FFFFFF";
}

/**
 * Admin theme (সেটিংস → ব্র্যান্ড ও থিম) as CSS custom properties overriding globals.css.
 * Only well-formed values get through, so nothing from the API can break out of the rule.
 */
export function themeCss({ colors, radius, fonts }: SiteSettings["theme"]): string {
  const c = Object.fromEntries(Object.entries(colors).filter(([, v]) => HEX.test(v))) as Partial<typeof colors>;
  const vars: Record<string, string | undefined> = {
    "--primary": c.primary,
    "--primary-foreground": c.primary && onColor(c.primary),
    "--ring": c.primary,
    "--chart-1": c.primary,
    "--sidebar-primary": c.primary,
    "--gold": c.accent,
    "--background": c.background,
    "--card": c.surface,
    "--popover": c.surface,
    "--sidebar": c.surface,
    "--foreground": c.text,
    "--card-foreground": c.text,
    "--popover-foreground": c.text,
    "--secondary-foreground": c.text,
    "--accent-foreground": c.text,
    "--muted-foreground": c.muted,
    "--border": c.border,
    "--input": c.border,
    "--sidebar-border": c.border,
    "--radius": Number.isFinite(radius) ? `${Math.min(Math.max(radius, 0), 28) / 16}rem` : undefined,
    "--font-heading-latin": fontVar(fonts.heading, "fraunces"),
    "--font-heading-bn": fontVar(fonts.heading_bn, "noto-serif-bengali"),
    "--font-body-latin": fontVar(fonts.body, "manrope"),
    "--font-body-bn": fontVar(fonts.body_bn, "noto-sans-bengali"),
  };

  // Soft grey fills follow the background/text pair, so a custom palette stays coherent.
  if (c.background && c.text && (c.background.toUpperCase() !== "#F8F8F6" || c.text.toUpperCase() !== "#171717")) {
    const soft = `color-mix(in srgb, ${c.text} 6%, ${c.background})`;
    Object.assign(vars, { "--secondary": soft, "--muted": soft, "--accent": soft, "--sidebar-accent": soft, "--placeholder": `color-mix(in srgb, ${c.text} 10%, ${c.background})` });
  }

  return `:root{${Object.entries(vars).filter(([, v]) => v).map(([k, v]) => `${k}:${v}`).join(";")}}`;
}
