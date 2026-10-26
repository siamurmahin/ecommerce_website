"use client";

import { useMutation } from "@tanstack/react-query";
import { BellRing, CheckCircle2, Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { inputClass } from "@/components/checkout/field";
import { Button } from "@/components/ui/button";
import { useCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { requestStockAlert } from "@/lib/api/customer";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

/** Out of stock: leave a phone number and get an SMS when it's back. */
export function StockAlert({ slug, variantId }: { slug: string; variantId: number | null }) {
  const t = useTranslations("stockAlert");
  const locale = useLocale();
  const { data: customer } = useCustomer();
  const [phone, setPhone] = useState<string | null>(null);
  const [website, setWebsite] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [sentFor, setSentFor] = useState<string | null>(null);
  const value = phone ?? customer?.phone ?? "";
  const key = `${slug}:${variantId ?? ""}`;

  const mutation = useMutation({
    mutationFn: (normalized: string) => requestStockAlert(slug, { phone: normalized, variant_id: variantId, ...(website ? { website } : {}) }, locale),
    onSuccess: () => setSentFor(key),
    onError: (e) => setError(e instanceof ApiError && e.status === 429 ? t("tooMany") : e instanceof ApiError && e.fieldErrors.phone ? t("invalidPhone") : t("failed")),
  });

  if (sentFor === key) {
    return (
      <p role="status" className="flex items-start gap-2 rounded-lg bg-primary/8 p-4 text-sm text-primary">
        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
        {t("done")}
      </p>
    );
  }

  return (
    <form
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        const normalized = normalizePhone(value);
        if (!BD_PHONE.test(normalized)) return setError(t("invalidPhone"));
        setError(null);
        mutation.mutate(normalized);
      }}
      className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4"
    >
      <p className="flex items-center gap-2 text-sm font-semibold">
        <BellRing className="size-4 text-primary" strokeWidth={1.8} />
        {t("title")}
      </p>
      <div className="flex gap-2">
        <input
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          value={value}
          onChange={(e) => setPhone(e.target.value)}
          placeholder="01XXXXXXXXX"
          aria-label={t("phone")}
          aria-invalid={Boolean(error)}
          className={cn(inputClass, "h-11 flex-1 tabular-nums")}
        />
        <input type="text" name="website" value={website} onChange={(e) => setWebsite(e.target.value)} tabIndex={-1} autoComplete="off" className="hidden" aria-hidden />
        <Button type="submit" disabled={mutation.isPending} className="h-11 rounded-full px-5">
          {mutation.isPending && <Loader2 className="size-4 animate-spin" />}
          {t("submit")}
        </Button>
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
    </form>
  );
}
