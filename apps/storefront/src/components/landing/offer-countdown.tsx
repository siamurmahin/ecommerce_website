"use client";

import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";

const pad = (n: number, locale: string) => formatNumber(n, locale).padStart(2, locale === "bn" ? "০" : "0");

/** Large hour/minute/second tiles for the landing offer block. Renders a same-size placeholder until mounted (the clock is the visitor's). */
export function OfferCountdown({ endsAt, inverse = false }: { endsAt: string; inverse?: boolean }) {
  const t = useTranslations("landing");
  const locale = useLocale();
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    const tick = () => setNow(Date.now());
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, []);

  const left = now === null ? null : Math.max(0, new Date(endsAt).getTime() - now);
  if (left === 0) return null;

  const s = Math.floor((left ?? 0) / 1000);
  const days = Math.floor(s / 86400);
  const tiles = [
    ...(days > 0 ? [[days, t("days")] as const] : []),
    [Math.floor((s % 86400) / 3600), t("hours")] as const,
    [Math.floor((s % 3600) / 60), t("minutes")] as const,
    [s % 60, t("seconds")] as const,
  ];

  return (
    <div className="flex justify-center gap-2 md:gap-3" role="timer" aria-live="off">
      {tiles.map(([value, label]) => (
        <span
          key={label}
          className={cn(
            "flex size-16 flex-col items-center justify-center rounded-md border md:size-20",
            inverse ? "border-primary-foreground/20 bg-primary-foreground/10" : "border-border bg-card",
          )}
        >
          <span className="text-2xl leading-none font-semibold tabular-nums md:text-3xl">{left === null ? "--" : pad(value, locale)}</span>
          <span className="mt-1 text-[11px] opacity-75">{label}</span>
        </span>
      ))}
    </div>
  );
}
