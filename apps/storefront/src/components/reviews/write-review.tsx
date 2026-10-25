"use client";

import { useQuery } from "@tanstack/react-query";
import { PenLine } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import { useCustomer } from "@/hooks/use-customer";
import { Link } from "@/i18n/navigation";
import { getReviewable } from "@/lib/api/customer";
import { cn } from "@/lib/utils";
import { ReviewDialog } from "./review-dialog";

type Target = { id: number; slug: string; name: string | null; image: string | null };

/** Signed out → sign-in link; signed in and received the product → review dialog; otherwise a note. */
export function WriteReview({ product }: { product: Target }) {
  const t = useTranslations("reviews");
  const locale = useLocale();
  const { data: customer } = useCustomer();
  const { data: reviewable } = useQuery({ queryKey: ["reviewable", locale], queryFn: () => getReviewable(locale), enabled: Boolean(customer) });
  const [open, setOpen] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const button = cn(buttonVariants({ variant: "outline" }), "h-11 self-start rounded-full px-5");

  if (customer === undefined) return null;
  if (customer === null) {
    return (
      <Link href={`/account?next=${encodeURIComponent(`/p/${product.slug}#reviews`)}`} className={button}>
        <PenLine className="size-4" />
        {t("loginToReview")}
      </Link>
    );
  }
  const canReview = reviewable?.some((p) => p.id === product.id);

  return (
    <>
      {submitted ? (
        <p className="text-sm text-primary">{t("thanks")}</p>
      ) : canReview ? (
        <button type="button" onClick={() => setOpen(true)} className={button}>
          <PenLine className="size-4" />
          {t("write")}
        </button>
      ) : reviewable ? (
        <p className="text-sm text-muted-foreground">{t("onlyBuyers")}</p>
      ) : null}
      {/* Stays mounted after submitting so the thank-you state can show. */}
      <ReviewDialog product={product} open={open} onOpenChange={setOpen} onSubmitted={() => setSubmitted(true)} />
    </>
  );
}
