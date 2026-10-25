"use client";

import { Banknote, Check, Copy, Smartphone } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import type { CheckoutOptions, PlaceOrderInput } from "@/lib/api/types";
import { formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Field, inputClass } from "./field";

type Method = CheckoutOptions["payment_methods"][number];
type MethodCode = PlaceOrderInput["payment_method"];
export type PaymentValues = { method: MethodCode; wallet: string; sender: string; trxId: string };
export type PaymentErrors = Partial<Record<"wallet" | "sender" | "trxId" | "method", string>>;

/**
 * How much has to be sent by mobile wallet before the order: the whole total for a wallet method,
 * the delivery charge for cash on delivery when the shop asks for it, otherwise nothing.
 * null = depends on a delivery charge we don't know yet (no district chosen).
 */
export function advanceFor(options: CheckoutOptions, method: string, total: number, deliveryCharge: number | null): number | null {
  if (method !== "cod") return deliveryCharge === null ? null : total;
  if (options.cod_advance !== "delivery") return 0;
  return deliveryCharge;
}

/** Method cards, and for wallet payments the number to send to plus sender/TrxID fields. */
export function PaymentSection({ options, value, onChange, errors, amount, total }: {
  options: CheckoutOptions;
  value: PaymentValues;
  onChange: (value: PaymentValues) => void;
  errors: PaymentErrors;
  /** What to send now (see advanceFor). */
  amount: number | null;
  total: number;
}) {
  const t = useTranslations("checkout");
  const locale = useLocale();
  const wallets = options.payment_methods.filter((m) => m.code !== "cod");
  const set = (patch: Partial<PaymentValues>) => onChange({ ...value, ...patch });
  const cod = value.method === "cod";
  const wallet = cod ? wallets.find((w) => w.code === value.wallet) : wallets.find((w) => w.code === value.method);
  const needsTransfer = amount === null ? cod && options.cod_advance === "delivery" : amount > 0;

  return (
    <section className="flex flex-col gap-4 rounded-lg bg-card p-5 sm:p-6">
      <h2 className="font-heading text-xl font-semibold">{t("payment")}</h2>
      <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label={t("payment")}>
        {options.payment_methods.map((m) => {
          const active = value.method === m.code;
          const Icon = m.code === "cod" ? Banknote : Smartphone;
          return (
            <label
              key={m.code}
              className={cn(
                "flex cursor-pointer items-center gap-3.5 rounded-lg border-2 p-4 transition-colors",
                active ? "border-primary bg-secondary/50" : "border-border hover:border-primary/40",
              )}
            >
              <input type="radio" name="payment_method" value={m.code} checked={active} onChange={() => set({ method: m.code as MethodCode })} className="size-4 accent-primary" />
              <Icon className="size-6 shrink-0 text-primary" strokeWidth={1.6} />
              <span className="flex flex-col">
                <span className="font-semibold">{t(`methods.${m.code}`)}</span>
                <span className="text-sm text-muted-foreground">
                  {m.code === "cod" ? (options.cod_advance === "delivery" ? t("codAdvanceNote") : t("codNote")) : t("walletNote")}
                </span>
              </span>
            </label>
          );
        })}
      </div>
      {errors.method && <p className="text-sm text-destructive">{errors.method}</p>}

      {needsTransfer && (
        <div className="flex flex-col gap-4 rounded-lg border border-border bg-background p-4 sm:p-5">
          {cod && (
            <div className="flex flex-col gap-2">
              <p className="text-sm">
                {amount === null
                  ? t("advancePending")
                  : t("advanceNote", { amount: formatTaka(amount, locale), rest: formatTaka(Math.max(0, total - amount), locale) })}
              </p>
              <span className="text-sm font-medium">{t("payWith")}</span>
              <div className="flex flex-wrap gap-2">
                {wallets.map((w) => (
                  <button
                    key={w.code}
                    type="button"
                    aria-pressed={value.wallet === w.code}
                    onClick={() => set({ wallet: w.code })}
                    className={cn(
                      "rounded-full border px-4 py-1.5 text-sm transition-colors",
                      value.wallet === w.code ? "border-primary bg-primary/5 font-semibold text-primary" : "border-border hover:border-primary/50",
                    )}
                  >
                    {t(`methods.${w.code}`)}
                  </button>
                ))}
              </div>
              {errors.wallet && <p className="text-sm text-destructive">{errors.wallet}</p>}
            </div>
          )}

          {wallet && <SendTo wallet={wallet} amount={amount} />}

          {wallet && (
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t("sender")} error={errors.sender}>
                {(props) => (
                  <input
                    {...props}
                    value={value.sender}
                    onChange={(e) => set({ sender: e.target.value })}
                    type="tel"
                    inputMode="tel"
                    placeholder={t("phonePlaceholder")}
                    className={cn(inputClass, "tabular-nums")}
                  />
                )}
              </Field>
              <Field label={t("trxId")} error={errors.trxId}>
                {(props) => (
                  <input
                    {...props}
                    value={value.trxId}
                    onChange={(e) => set({ trxId: e.target.value.replace(/\s/g, "").toUpperCase() })}
                    autoCapitalize="characters"
                    autoComplete="off"
                    spellCheck={false}
                    maxLength={30}
                    placeholder={t("trxPlaceholder")}
                    className={cn(inputClass, "font-mono tracking-wider")}
                  />
                )}
              </Field>
              <p className="text-xs text-muted-foreground sm:col-span-2">{t("trxHelp")}</p>
            </div>
          )}
        </div>
      )}
    </section>
  );
}

function SendTo({ wallet, amount }: { wallet: Method; amount: number | null }) {
  const t = useTranslations("checkout");
  const locale = useLocale();
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(wallet.number ?? "");
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {}
  };

  return (
    <div className="flex flex-col gap-2 rounded-md bg-secondary/60 p-4">
      <p className="text-sm">
        {amount ? t.rich("sendAmount", { amount: formatTaka(amount, locale), wallet: t(`methods.${wallet.code}`), b: (c) => <strong className="font-semibold">{c}</strong> }) : t("amountPending")}
      </p>
      <div className="flex items-center gap-3">
        <span className="font-heading text-2xl font-semibold tracking-wide tabular-nums">{wallet.number}</span>
        <button type="button" onClick={copy} className="inline-flex items-center gap-1 rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold transition-colors hover:border-primary">
          {copied ? <Check className="size-3.5 text-primary" /> : <Copy className="size-3.5" />}
          {copied ? t("copied") : t("copy")}
        </button>
      </div>
      <p className="text-sm text-muted-foreground">{t(`accountTypes.${wallet.account_type ?? "personal"}`)}</p>
      {wallet.instructions && <p className="text-sm whitespace-pre-line">{wallet.instructions}</p>}
    </div>
  );
}
