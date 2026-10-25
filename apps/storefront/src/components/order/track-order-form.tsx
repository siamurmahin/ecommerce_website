"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Field, inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api/client";
import { trackOrder } from "@/lib/api/cart";
import { BD_PHONE, normalizePhone } from "@/lib/phone";
import { cn } from "@/lib/utils";

type Values = { number: string; phone: string };

/** Order number + phone → the order page (status timeline). No login needed. */
export function TrackOrderForm() {
  const t = useTranslations("track");
  const tc = useTranslations("checkout");
  const locale = useLocale();
  const router = useRouter();
  const [formError, setFormError] = useState<string | null>(null);

  const schema = useMemo(
    () =>
      z.object({
        number: z.string().trim().min(3, t("errors.number")).max(20, t("errors.number")),
        phone: z.string().refine((v) => BD_PHONE.test(normalizePhone(v)), tc("errors.phone")),
      }),
    [t, tc],
  );

  const { register, handleSubmit, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { number: "", phone: "" },
    mode: "onTouched",
  });

  const mutation = useMutation({
    mutationFn: (values: Values) => trackOrder({ number: values.number.trim(), phone: normalizePhone(values.phone) }, locale),
    onSuccess: ({ number, access_token }) => router.push(`/order/${number}?token=${access_token}&from=track`),
    onError: (error) => {
      if (error instanceof ApiError && error.status === 404) return setFormError(t("errors.notFound"));
      if (error instanceof ApiError && error.status === 429) return setFormError(tc("errors.tooMany"));
      setFormError(tc("errors.generic"));
    },
  });

  return (
    <form
      noValidate
      onSubmit={handleSubmit((values) => {
        setFormError(null);
        mutation.mutate(values);
      })}
      className="flex flex-col gap-4 rounded-lg bg-card p-5 sm:p-6"
    >
      <Field label={t("number")} error={formState.errors.number?.message}>
        {(props) => (
          <input {...props} {...register("number")} autoCapitalize="characters" placeholder={t("numberPlaceholder")} className={cn(inputClass, "tracking-wide uppercase tabular-nums")} />
        )}
      </Field>
      <Field label={tc("phone")} error={formState.errors.phone?.message}>
        {(props) => (
          <input {...props} {...register("phone")} type="tel" inputMode="tel" autoComplete="tel" placeholder={tc("phonePlaceholder")} className={cn(inputClass, "tabular-nums")} />
        )}
      </Field>

      {formError && (
        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {formError}
        </p>
      )}

      <button type="submit" disabled={mutation.isPending || mutation.isSuccess} className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full text-[15px]")}>
        {mutation.isPending || mutation.isSuccess ? <Loader2 className="size-4 animate-spin" /> : null}
        {t("submit")}
      </button>
      <p className="text-center text-xs text-muted-foreground">{t("hint")}</p>
    </form>
  );
}
