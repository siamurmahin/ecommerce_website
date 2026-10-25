"use client";

import { useMutation } from "@tanstack/react-query";
import { CheckCircle2, Loader2, MessageCircleQuestion } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useCustomer } from "@/hooks/use-customer";
import { ApiError } from "@/lib/api/client";
import { askQuestion } from "@/lib/api/customer";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

type Errors = Partial<Record<"name" | "phone" | "question" | "form", string>>;

/** Inline ask form. Questions appear on the page once the shop answers them. */
export function AskQuestion({ slug }: { slug: string }) {
  const t = useTranslations("questions");
  const locale = useLocale();
  const { data: customer } = useCustomer();
  const [open, setOpen] = useState(false);
  const [sent, setSent] = useState(false);
  // null = untouched, so a signed-in customer's details show until they type.
  const [name, setName] = useState<string | null>(null);
  const [phone, setPhone] = useState<string | null>(null);
  const [question, setQuestion] = useState("");
  const [website, setWebsite] = useState("");
  const [errors, setErrors] = useState<Errors>({});

  const nameValue = name ?? customer?.name ?? "";
  const phoneValue = phone ?? customer?.phone ?? "";

  const mutation = useMutation({
    mutationFn: (input: Parameters<typeof askQuestion>[1]) => askQuestion(slug, input, locale),
    onSuccess: () => {
      setSent(true);
      setOpen(false);
      setQuestion("");
    },
    onError: (error) => {
      const fields = error instanceof ApiError ? error.fieldErrors : {};
      if (error instanceof ApiError && error.status === 429) setErrors({ form: t("errors.tooMany") });
      else if (Object.keys(fields).length > 0) setErrors({ name: fields.name, phone: fields.phone, question: fields.question, form: fields.website ? t("errors.generic") : undefined });
      else setErrors({ form: t("errors.generic") });
    },
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const normalized = phoneValue.trim() ? normalizePhone(phoneValue) : null;
    const next: Errors = {};
    if (nameValue.trim().length < 2) next.name = t("errors.name");
    if (normalized && !BD_PHONE.test(normalized)) next.phone = t("errors.phone");
    if (question.trim().length < 5) next.question = t("errors.question");
    setErrors(next);
    if (Object.keys(next).length > 0) return;
    mutation.mutate({ name: nameValue.trim(), phone: normalized, question: question.trim(), ...(website ? { website } : {}) });
  }

  if (!open) {
    return (
      <div className="flex flex-col gap-3">
        {sent && (
          <p role="status" className="flex items-start gap-2 rounded-md bg-primary/8 p-3 text-sm text-primary">
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
            {t("sent")}
          </p>
        )}
        <button type="button" onClick={() => setOpen(true)} className={cn(buttonVariants({ variant: "outline" }), "h-11 self-start rounded-full px-5")}>
          <MessageCircleQuestion className="size-4" />
          {sent ? t("askAnother") : t("ask")}
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-3 rounded-lg bg-card p-4">
      <Field label={t("question")} error={errors.question}>
        {(props) => (
          <textarea
            {...props}
            value={question}
            onChange={(e) => setQuestion(e.target.value)}
            maxLength={500}
            rows={3}
            autoFocus
            placeholder={t("placeholder")}
            className={cn(inputClass, "h-auto resize-none py-3")}
          />
        )}
      </Field>
      <Field label={t("name")} error={errors.name}>
        {(props) => <input {...props} value={nameValue} onChange={(e) => setName(e.target.value)} maxLength={100} autoComplete="name" className={inputClass} />}
      </Field>
      <Field label={t("phone")} error={errors.phone}>
        {(props) => <input {...props} value={phoneValue} onChange={(e) => setPhone(e.target.value)} inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" className={inputClass} />}
      </Field>
      {/* Honeypot for bots; hidden from people and screen readers. */}
      <input type="text" name="website" value={website} onChange={(e) => setWebsite(e.target.value)} tabIndex={-1} autoComplete="off" aria-hidden className="hidden" />
      {errors.form && <p className="text-sm text-destructive">{errors.form}</p>}
      <div className="flex gap-2">
        <button type="submit" disabled={mutation.isPending} className={cn(buttonVariants(), "h-11 flex-1 rounded-full")}>
          {mutation.isPending && <Loader2 className="size-4 animate-spin" />}
          {t("submit")}
        </button>
        <button type="button" onClick={() => setOpen(false)} className={cn(buttonVariants({ variant: "ghost" }), "h-11 rounded-full px-4")}>
          {t("cancel")}
        </button>
      </div>
    </form>
  );
}
