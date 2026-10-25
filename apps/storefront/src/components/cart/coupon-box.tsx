"use client";

import { Check, Loader2, TicketPercent, X } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import type { Quote } from "@/lib/api/types";
import { formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { useCart } from "@/stores/cart";

/** Coupon entry; the code lives in the cart store and every quote re-checks it. */
export function CouponBox({ quote, loading }: { quote: Quote | undefined; loading: boolean }) {
  const t = useTranslations("cart.coupon");
  const locale = useLocale();
  const coupon = useCart((s) => s.coupon);
  const setCoupon = useCart((s) => s.setCoupon);
  const [code, setCode] = useState("");
  const [open, setOpen] = useState(Boolean(coupon));

  const result = quote?.coupon && coupon && quote.coupon.code === coupon.trim().toUpperCase() ? quote.coupon : null;
  const saving = quote?.discounts.find((d) => d.type === "coupon")?.amount ?? 0;

  if (coupon && result?.applied) {
    return (
      <div className="flex items-center gap-3 rounded-md bg-secondary px-3.5 py-2.5 text-sm">
        <Check className="size-4 shrink-0 text-primary" strokeWidth={2.4} />
        <span className="flex-1">
          <strong className="font-semibold">{result.code}</strong>{" "}
          {result.free_shipping ? t("freeShipping") : saving > 0 && t("saved", { amount: formatTaka(saving, locale) })}
        </span>
        <button type="button" onClick={() => setCoupon(null)} aria-label={t("remove")} className="rounded-full p-1 text-muted-foreground hover:text-foreground">
          <X className="size-4" />
        </button>
      </div>
    );
  }

  if (!open) {
    return (
      <button type="button" onClick={() => setOpen(true)} className="flex items-center gap-2 self-start text-sm font-medium text-primary underline-offset-4 hover:underline">
        <TicketPercent className="size-4" strokeWidth={1.8} />
        {t("have")}
      </button>
    );
  }

  const error = result?.error ?? null;

  return (
    <form
      className="flex flex-col gap-1.5"
      onSubmit={(e) => {
        e.preventDefault();
        if (code.trim()) setCoupon(code.trim());
      }}
    >
      <div className="flex gap-2">
        <input
          value={code}
          onChange={(e) => setCode(e.target.value)}
          placeholder={t("placeholder")}
          aria-label={t("placeholder")}
          aria-invalid={Boolean(error)}
          autoCapitalize="characters"
          className={cn(
            "h-11 min-w-0 flex-1 rounded-md border border-border bg-background px-3.5 text-sm uppercase outline-none placeholder:normal-case focus:border-primary focus:ring-3 focus:ring-primary/15",
            error && "border-destructive",
          )}
        />
        <button type="submit" disabled={!code.trim() || loading} className="h-11 rounded-md bg-foreground px-4 text-sm font-semibold text-background disabled:opacity-50">
          {loading && coupon ? <Loader2 className="size-4 animate-spin" /> : t("apply")}
        </button>
      </div>
      {error && (
        <p role="alert" className="text-sm text-destructive">
          {t(`errors.${error}`, { amount: formatTaka(result?.min_subtotal ?? 0, locale) })}
        </p>
      )}
    </form>
  );
}
