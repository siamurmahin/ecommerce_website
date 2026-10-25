"use client";

import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useSetCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { sendOtp, verifyOtp } from "@/lib/api/customer";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

/** Phone → SMS code → signed in. New numbers are asked for a name on the code step. */
export function LoginForm({ onDone }: { onDone?: () => void }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const setCustomer = useSetCustomer();
  const [step, setStep] = useState<"phone" | "code">("phone");
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [isNew, setIsNew] = useState(false);
  const [wait, setWait] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (wait <= 0) return;
    const id = setTimeout(() => setWait((w) => w - 1), 1000);
    return () => clearTimeout(id);
  }, [wait]);

  const fail = (e: unknown, fallback: string) => {
    if (e instanceof ApiError && e.status === 429) {
      const seconds = Number(/(\d+)/.exec(Object.values(e.fieldErrors)[0] ?? "")?.[1] ?? 60);
      setWait(seconds);
      return setError(t("resendIn", { seconds }));
    }
    if (e instanceof ApiError && e.status === 503) return setError(t("errors.sms"));
    if (e instanceof ApiError && e.status === 422) return setError(Object.values(e.fieldErrors)[0] ?? fallback);
    setError(t("errors.generic"));
  };

  const requestCode = async () => {
    const normalized = normalizePhone(phone);
    if (!BD_PHONE.test(normalized)) return setError(t("errors.phone"));
    setBusy(true);
    setError(null);
    try {
      const sent = await sendOtp(normalized, locale);
      setIsNew(sent.is_new);
      setWait(sent.resend_after);
      setCode("");
      setStep("code");
    } catch (e) {
      fail(e, t("errors.phone"));
    } finally {
      setBusy(false);
    }
  };

  const confirm = async () => {
    if (!/^\d{6}$/.test(code)) return setError(t("errors.code"));
    if (isNew && name.trim().length < 2) return setError(t("errors.name"));
    setBusy(true);
    setError(null);
    try {
      const { customer } = await verifyOtp({ phone: normalizePhone(phone), code, name: isNew ? name.trim() : null }, locale);
      setCustomer(customer);
      onDone?.();
    } catch (e) {
      fail(e, t("errors.code"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        if (step === "phone") requestCode();
        else confirm();
      }}
      className="flex flex-col gap-5"
    >
      <div className="flex flex-col gap-1.5">
        <h2 className="font-heading text-2xl font-semibold tracking-tight">{step === "phone" ? t("loginTitle") : t("codeTitle")}</h2>
        <p className="text-sm text-muted-foreground">{step === "phone" ? t("loginNote") : t("codeSent", { phone: normalizePhone(phone) })}</p>
      </div>

      {step === "phone" ? (
        <Field label={t("phone")}>
          {(props) => (
            <input {...props} value={phone} onChange={(e) => setPhone(e.target.value)} type="tel" inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" autoFocus className={cn(inputClass, "tabular-nums")} />
          )}
        </Field>
      ) : (
        <>
          <Field label={t("code")}>
            {(props) => (
              <input
                {...props}
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/[^\d০-৯]/g, "").replace(/[০-৯]/g, (d) => String("০১২৩৪৫৬৭৮৯".indexOf(d))).slice(0, 6))}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                className={cn(inputClass, "text-center text-xl tracking-[0.5em] tabular-nums")}
              />
            )}
          </Field>
          {isNew && (
            <Field label={t("name")}>
              {(props) => <input {...props} value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" placeholder={t("namePlaceholder")} className={inputClass} />}
            </Field>
          )}
        </>
      )}

      {error && (
        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      )}

      <button type="submit" disabled={busy} className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full text-[15px]")}>
        {busy && <Loader2 className="size-4 animate-spin" />}
        {step === "phone" ? t("sendCode") : t("verify")}
      </button>

      {step === "code" && (
        <div className="flex items-center justify-between gap-3 text-sm">
          <button type="button" onClick={() => { setStep("phone"); setError(null); }} className="font-medium text-muted-foreground hover:text-foreground">
            {t("changePhone")}
          </button>
          {wait > 0 ? (
            <span className="text-muted-foreground tabular-nums">{t("resendIn", { seconds: wait })}</span>
          ) : (
            <button type="button" onClick={requestCode} disabled={busy} className="font-semibold text-primary hover:underline">
              {t("resend")}
            </button>
          )}
        </div>
      )}
    </form>
  );
}
