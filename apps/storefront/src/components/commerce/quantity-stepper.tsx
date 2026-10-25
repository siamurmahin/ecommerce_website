"use client";

import { Minus, Plus } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";

type Props = {
  value: number;
  max: number;
  onChange: (value: number) => void;
  size?: "sm" | "md";
  className?: string;
};

export function QuantityStepper({ value, max, onChange, size = "md", className }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const button = cn("grid h-full place-items-center transition-colors hover:text-primary disabled:opacity-35", size === "sm" ? "w-8" : "w-11");

  return (
    <div className={cn("inline-flex items-center rounded-full border border-border bg-card", size === "sm" ? "h-8" : "h-12", className)}>
      <button type="button" className={button} onClick={() => onChange(value - 1)} disabled={value <= 1} aria-label={t("decrease")}>
        <Minus className={size === "sm" ? "size-3.5" : "size-4"} />
      </button>
      <span className={cn("text-center text-sm tabular-nums", size === "sm" ? "w-6" : "w-8")} aria-live="polite">
        {formatNumber(value, locale)}
      </span>
      <button type="button" className={button} onClick={() => onChange(value + 1)} disabled={value >= max} aria-label={t("increase")}>
        <Plus className={size === "sm" ? "size-3.5" : "size-4"} />
      </button>
    </div>
  );
}
