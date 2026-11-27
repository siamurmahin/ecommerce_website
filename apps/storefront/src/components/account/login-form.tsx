"use client";

import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useSetCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { sendEmailOtp, sendOtp, verifyEmailOtp, verifyOtp } from "@/lib/api/customer";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

/** Phone (SMS) or email → 6-digit code → signed in. New accounts are asked for a name on the code step. */
export function LoginForm({ onDone }: { onDone?: () => void }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const setCustomer = useSetCustomer();
  const [step, setStep] = useState<"phone" | "code">("phone");
  const [method, setMethod] = useState<"phone" | "email">("phone");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
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
    if (e instanceof ApiError && e.status === 503) return setError(t(method === "email" ? "errors.mail" : "errors.sms"));
    if (e instanceof ApiError && e.status === 422) return setError(Object.values(e.fieldErrors)[0] ?? fallback);
    setError(t("errors.generic"));
  };

  const cleanEmail = email.trim().toLowerCase();

  const requestCode = async () => {
    const normalized = normalizePhone(phone);
    if (method === "phone" && !BD_PHONE.test(normalized)) return setError(t("errors.phone"));
    if (method === "email" && !/^\S+@\S+\.\S+$/.test(cleanEmail)) return setError(t("errors.email"));
    setBusy(true);
    setError(null);
    try {
      const sent = method === "phone" ? await sendOtp(normalized, locale) : await sendEmailOtp(cleanEmail, locale);
      setIsNew(sent.is_new);
      setWait(sent.resend_after);
      setCode("");
      setStep("code");
    } catch (e) {
      fail(e, t(method === "phone" ? "errors.phone" : "errors.email"));
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
      const newName = isNew ? name.trim() : null;
      const { customer } =
        method === "phone"
          ? await verifyOtp({ phone: normalizePhone(phone), code, name: newName }, locale)
          : await verifyEmailOtp({ email: cleanEmail, code, name: newName }, locale);
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
        <p className="text-sm text-muted-foreground">
          {step === "phone"
            ? t(method === "phone" ? "loginNote" : "loginNoteEmail")
            : method === "phone"
              ? t("codeSent", { phone: normalizePhone(phone) })
              : t("codeSentEmail", { email: cleanEmail })}
        </p>
      </div>

      {step === "phone" ? (
        <>
          <div role="radiogroup" className="grid grid-cols-2 gap-1 rounded-full bg-muted p-1 text-sm font-medium">
            {(["phone", "email"] as const).map((m) => (
              <button
                key={m}
                type="button"
                role="radio"
                aria-checked={method === m}
                onClick={() => {
                  setMethod(m);
                  setError(null);
                }}
                className={cn("h-9 rounded-full transition-colors", method === m ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground")}
              >
                {t(m === "phone" ? "byPhone" : "byEmail")}
              </button>
            ))}
          </div>
          {method === "phone" ? (
            <Field label={t("phone")}>
              {(props) => (
                <input {...props} value={phone} onChange={(e) => setPhone(e.target.value)} type="tel" inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" autoFocus className={cn(inputClass, "tabular-nums")} />
              )}
            </Field>
          ) : (
            <Field label={t("emailAddress")}>
              {(props) => (
                <input {...props} value={email} onChange={(e) => setEmail(e.target.value)} type="email" inputMode="email" autoComplete="email" placeholder="you@example.com" autoFocus className={inputClass} />
              )}
            </Field>
          )}
        </>
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
            {t(method === "phone" ? "changePhone" : "changeEmail")}
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
