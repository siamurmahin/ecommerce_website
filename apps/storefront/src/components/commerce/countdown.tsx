"use client";

import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";

const pad = (n: number, locale: string) => formatNumber(n, locale).padStart(2, locale === "bn" ? "০" : "0");

/** Time left until `endsAt` (ISO), ticking each second. Renders nothing until mounted (the clock is the visitor's). */
export function Countdown({ endsAt, className, label = true, inverse = false }: { endsAt: string; className?: string; label?: boolean; inverse?: boolean }) {
  const t = useTranslations("flash");
  const locale = useLocale();
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    const tick = () => setNow(Date.now());
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, []);

  if (now === null) return <span className={cn("inline-block h-7 w-36", className)} aria-hidden />;

  const left = Math.max(0, new Date(endsAt).getTime() - now);
  if (left === 0) return <span className={cn("text-sm font-semibold text-muted-foreground", className)}>{t("ended")}</span>;

  const box = inverse ? "bg-background text-foreground" : "bg-foreground text-background";
  const s = Math.floor(left / 1000);
  const days = Math.floor(s / 86400);
  const parts = [Math.floor((s % 86400) / 3600), Math.floor((s % 3600) / 60), s % 60];

  return (
    <span className={cn("inline-flex items-center gap-2 text-sm", className)} role="timer" aria-live="off">
      {label && <span className="font-semibold">{t("endsIn")}</span>}
      <span className="flex items-center gap-1 font-semibold tabular-nums">
        {days > 0 && <span className={cn("rounded-md px-1.5 py-0.5", box)}>{t("days", { count: formatNumber(days, locale) })}</span>}
        {parts.map((p, i) => (
          <span key={i} className="flex items-center gap-1">
            {i > 0 && <span aria-hidden>:</span>}
            <span className={cn("min-w-7 rounded-md px-1.5 py-0.5 text-center", box)}>{pad(p, locale)}</span>
          </span>
        ))}
      </span>
    </span>
  );
}
