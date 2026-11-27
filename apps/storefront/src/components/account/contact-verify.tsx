"use client";

import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useSetCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { confirmEmail, confirmPhone, sendEmailChangeOtp, sendOtp } from "@/lib/api/customer";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

/**
 * Inline code check on the profile: adds a phone to an email account, or confirms the saved email so it can be used
 * to sign in. Adding a phone may merge with the account on that number; the new account replaces the cached one.
 */
export function ContactVerify({ kind, email, onClose }: { kind: "phone" | "email"; email?: string; onClose: () => void }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const setCustomer = useSetCustomer();
  const [phone, setPhone] = useState("");
  const [sent, setSent] = useState(false);
  const [code, setCode] = useState("");
  const [wait, setWait] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (wait <= 0) return;
    const id = setTimeout(() => setWait((w) => w - 1), 1000);
    return () => clearTimeout(id);
  }, [wait]);

  const fail = (e: unknown) => {
    if (e instanceof ApiError && e.status === 429) {
      const seconds = Number(/(\d+)/.exec(Object.values(e.fieldErrors)[0] ?? "")?.[1] ?? 60);
      setWait(seconds);
      return setError(t("resendIn", { seconds }));
    }
    if (e instanceof ApiError && e.status === 503) return setError(t(kind === "email" ? "errors.mail" : "errors.sms"));
    if (e instanceof ApiError && e.status === 422) return setError(Object.values(e.fieldErrors)[0] ?? t("errors.generic"));
    setError(t("errors.generic"));
  };

  const run = async (task: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await task();
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
    }
  };

  const send = () => {
    if (kind === "phone" && !BD_PHONE.test(normalizePhone(phone))) return setError(t("errors.phone"));
    run(async () => {
      const res = kind === "phone" ? await sendOtp(normalizePhone(phone), locale) : await sendEmailChangeOtp(email ?? "", locale);
      setWait(res.resend_after);
      setCode("");
      setSent(true);
    });
  };

  const confirm = () => {
    if (!/^\d{6}$/.test(code)) return setError(t("errors.code"));
    run(async () => {
      setCustomer(kind === "phone" ? await confirmPhone({ phone: normalizePhone(phone), code }, locale) : await confirmEmail({ email: email ?? "", code }, locale));
      onClose();
    });
  };

  return (
    <div className="flex flex-col gap-3 rounded-md border border-border p-4">
      {kind === "phone" && <p className="text-sm text-muted-foreground">{t("addPhoneNote")}</p>}
      {kind === "phone" && !sent && (
        <Field label={t("phone")}>
          {(props) => (
            <input {...props} value={phone} onChange={(e) => setPhone(e.target.value)} type="tel" inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" autoFocus className={cn(inputClass, "tabular-nums")} />
          )}
        </Field>
      )}
      {sent && (
        <>
          <p className="text-sm text-muted-foreground">
            {kind === "phone" ? t("codeSent", { phone: normalizePhone(phone) }) : t("codeSentEmail", { email: email ?? "" })}
          </p>
          <Field label={t("code")}>
            {(props) => (
              <input
                {...props}
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/[^\d০-৯]/g, "").replace(/[০-৯]/g, (d) => String("০১২৩৪৫৬৭৮৯".indexOf(d))).slice(0, 6))}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                className={cn(inputClass, "text-center text-lg tracking-[0.5em] tabular-nums")}
              />
            )}
          </Field>
        </>
      )}
      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
      <div className="flex flex-wrap items-center gap-3 text-sm">
        <button type="button" onClick={sent ? confirm : send} disabled={busy} className={cn(buttonVariants({ size: "sm" }), "h-9 rounded-full px-4")}>
          {busy && <Loader2 className="size-4 animate-spin" />}
          {sent ? t("confirmCode") : t("sendCode")}
        </button>
        {sent &&
          (wait > 0 ? (
            <span className="text-muted-foreground tabular-nums">{t("resendIn", { seconds: wait })}</span>
          ) : (
            <button type="button" onClick={send} disabled={busy} className="font-semibold text-primary hover:underline">
              {t("resend")}
            </button>
          ))}
        <button type="button" onClick={onClose} className="ml-auto font-medium text-muted-foreground hover:text-foreground">
          {t("cancelVerify")}
        </button>
      </div>
    </div>
  );
}
