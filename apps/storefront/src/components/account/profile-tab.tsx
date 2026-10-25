"use client";

import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useSetCustomer } from "@/hooks/use-customer";
import { updateProfile } from "@/lib/api/customer";
import type { Customer } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export function ProfileTab({ customer }: { customer: Customer }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const setCustomer = useSetCustomer();
  const [name, setName] = useState(customer.name ?? "");
  const [email, setEmail] = useState(customer.email ?? "");
  const [state, setState] = useState<"idle" | "busy" | "saved" | { error: string }>("idle");

  const save = async () => {
    if (name.trim().length < 2) return setState({ error: t("errors.name") });
    if (email && !/^\S+@\S+\.\S+$/.test(email)) return setState({ error: t("errors.email") });
    setState("busy");
    try {
      setCustomer(await updateProfile({ name: name.trim(), email: email.trim() || null }, locale));
      setState("saved");
    } catch {
      setState({ error: t("errors.generic") });
    }
  };

  return (
    <form
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        save();
      }}
      className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6"
    >
      <Field label={t("phone")}>{(props) => <input {...props} value={customer.phone} disabled className={cn(inputClass, "tabular-nums opacity-70")} />}</Field>
      <Field label={t("name")}>{(props) => <input {...props} value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" className={inputClass} />}</Field>
      <Field label={t("email")}>
        {(props) => <input {...props} value={email} onChange={(e) => setEmail(e.target.value)} type="email" autoComplete="email" className={inputClass} />}
      </Field>
      {typeof state === "object" && (
        <p role="alert" className="text-sm text-destructive">
          {state.error}
        </p>
      )}
      <div className="flex items-center gap-4">
        <button type="submit" disabled={state === "busy"} className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {state === "busy" && <Loader2 className="size-4 animate-spin" />}
          {t("save")}
        </button>
        {state === "saved" && <span role="status" className="text-sm font-medium text-primary">{t("saved")}</span>}
      </div>
    </form>
  );
}
