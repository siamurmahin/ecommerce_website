"use client";

import { useMutation } from "@tanstack/react-query";
import { CheckCircle2, Loader2, RotateCcw } from "lucide-react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { inputClass } from "@/components/checkout/field";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/lib/api/client";
import { requestReturn } from "@/lib/api/customer";
import type { Order, ReturnReason, ReturnType } from "@/lib/api/types";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";

const REASONS: ReturnReason[] = ["damaged", "wrong_item", "size", "not_as_described", "quality", "changed_mind", "other"];

/** Delivered order: ask to return or exchange some items, and see earlier requests. */
export function ReturnRequest({ order, token }: { order: Order; token: string }) {
  const t = useTranslations("returns");
  const locale = useLocale();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [type, setType] = useState<ReturnType>("return");
  const [reason, setReason] = useState<ReturnReason | "">("");
  const [note, setNote] = useState("");
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const mutation = useMutation({
    mutationFn: () =>
      requestReturn(
        order.number ?? "",
        {
          token,
          type,
          reason: reason as ReturnReason,
          note: note.trim() || undefined,
          items: Object.entries(quantities).filter(([, q]) => q > 0).map(([id, quantity]) => ({ order_item_id: Number(id), quantity })),
        },
        locale,
      ),
    onSuccess: () => {
      setDone(true);
      setOpen(false);
      router.refresh();
    },
    onError: (e) => setError(e instanceof ApiError && e.status === 429 ? t("tooMany") : e instanceof ApiError && e.message ? e.message : t("failed")),
  });

  if (!order.returnable && order.returns.length === 0) return null;

  return (
    <section className="flex flex-col gap-4 rounded-lg bg-card p-5 sm:p-6">
      <h2 className="flex items-center gap-2 font-heading text-xl font-semibold">
        <RotateCcw className="size-5 text-primary" strokeWidth={1.8} />
        {t("title")}
      </h2>

      {order.returns.length > 0 && (
        <ul className="flex flex-col gap-2 text-sm">
          {order.returns.map((r) => (
            <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-background px-3 py-2">
              <span>{t(`types.${r.type}`)}</span>
              <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-primary">{t(`statuses.${r.status}`)}</span>
            </li>
          ))}
        </ul>
      )}

      {done && (
        <p role="status" className="flex items-start gap-2 rounded-lg bg-primary/8 p-4 text-sm text-primary">
          <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
          {t("done")}
        </p>
      )}

      {order.returnable && !open && !done && (
        <>
          <p className="text-sm text-muted-foreground">{order.return_policy}</p>
          <Button variant="outline" onClick={() => setOpen(true)} className="h-11 self-start rounded-full px-5">
            {t("open")}
          </Button>
        </>
      )}

      {order.returnable && open && (
        <form
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            if (!Object.values(quantities).some((q) => q > 0)) return setError(t("pickItem"));
            if (!reason) return setError(t("pickReason"));
            setError(null);
            mutation.mutate();
          }}
          className="flex flex-col gap-4"
        >
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium">{t("type")}</legend>
            <div className="grid gap-2 sm:grid-cols-2">
              {(["return", "exchange"] as const).map((value) => (
                <label
                  key={value}
                  className={cn(
                    "flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-3 text-sm",
                    type === value ? "border-primary bg-primary/5 font-medium" : "border-border",
                  )}
                >
                  <input type="radio" name="type" value={value} checked={type === value} onChange={() => setType(value)} className="accent-primary" />
                  {t(`types.${value}`)}
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium">{t("items")}</legend>
            {order.items.map((item) => (
              <div key={item.id} className="flex items-center justify-between gap-3 rounded-md bg-background px-3 py-2 text-sm">
                <span className="min-w-0 flex-1 truncate">{[item.name, item.variant_label].filter(Boolean).join(" · ")}</span>
                <select
                  value={quantities[item.id] ?? 0}
                  onChange={(e) => setQuantities((q) => ({ ...q, [item.id]: Number(e.target.value) }))}
                  aria-label={item.name}
                  className={cn(inputClass, "h-9 w-20 tabular-nums")}
                >
                  {Array.from({ length: item.quantity + 1 }, (_, n) => (
                    <option key={n} value={n}>
                      {formatNumber(n, locale)}
                    </option>
                  ))}
                </select>
              </div>
            ))}
          </fieldset>

          <label className="flex flex-col gap-2 text-sm font-medium">
            {t("reason")}
            <select value={reason} onChange={(e) => setReason(e.target.value as ReturnReason)} className={cn(inputClass, "h-11")}>
              <option value="" disabled>
                —
              </option>
              {REASONS.map((r) => (
                <option key={r} value={r}>
                  {t(`reasons.${r}`)}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-2 text-sm font-medium">
            {t("note")}
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              maxLength={1000}
              rows={3}
              placeholder={t("notePlaceholder")}
              className={cn(inputClass, "py-2.5 font-normal")}
            />
          </label>

          {error && <p className="text-sm text-destructive">{error}</p>}

          <div className="flex gap-2">
            <Button type="submit" disabled={mutation.isPending} className="h-11 rounded-full px-6">
              {mutation.isPending && <Loader2 className="size-4 animate-spin" />}
              {t("submit")}
            </Button>
            <Button type="button" variant="ghost" onClick={() => setOpen(false)} className="h-11 rounded-full px-5">
              {t("cancel")}
            </Button>
          </div>
        </form>
      )}
    </section>
  );
}
