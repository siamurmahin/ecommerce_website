"use client";

import { Truck } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

/** "Add ৳X more for free delivery" bar. Renders nothing when the admin threshold is off (0). */
export function FreeDeliveryProgress({ subtotal, threshold, className }: { subtotal: number; threshold: number; className?: string }) {
  const t = useTranslations("cart");
  const locale = useLocale();
  if (threshold <= 0) return null;

  const remaining = Math.max(0, threshold - subtotal);
  const progress = Math.min(1, subtotal / threshold);

  return (
    <div className={cn("flex flex-col gap-2 text-sm", className)}>
      <p className="flex items-center gap-2">
        <Truck className="size-4 shrink-0 text-primary" strokeWidth={1.8} />
        {remaining === 0 ? (
          <span className="font-medium text-primary">{t("freeDeliveryUnlocked")}</span>
        ) : (
          <span>{t("freeDeliveryProgress", { amount: formatTaka(remaining, locale) })}</span>
        )}
      </p>
      <div className="h-1.5 overflow-hidden rounded-full bg-secondary" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(progress * 100)}>
        <div className="h-full origin-left rounded-full bg-primary transition-transform duration-500 ease-out" style={{ transform: `scaleX(${progress})` }} />
      </div>
    </div>
  );
}
