const formatters = new Map<string, Intl.NumberFormat>();

/** Poisha → "৳২,৪৫০" (Bangla digits for bn, Latin for en). */
export function formatTaka(poisha: number, locale: string): string {
  const key = locale === "en" ? "en-BD" : "bn-BD";
  let fmt = formatters.get(key);
  if (!fmt) {
    fmt = new Intl.NumberFormat(key, { maximumFractionDigits: 2 });
    formatters.set(key, fmt);
  }
  return `৳${fmt.format(poisha / 100)}`;
}

export function formatNumber(value: number, locale: string): string {
  return new Intl.NumberFormat(locale === "en" ? "en-BD" : "bn-BD").format(value);
}
