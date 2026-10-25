"use client";

import { useLocale, useTranslations } from "next-intl";
import type { Quote } from "@/lib/api/types";
import { formatTaka } from "@/lib/format";

type Props = {
  subtotal: number;
  /** Bundle and coupon savings from the quote (already taken off `total`). */
  discounts?: Quote["discounts"];
  /** null = not known yet (no district chosen). */
  deliveryCharge: number | null;
  total: number;
};

export function SummaryRows({ subtotal, discounts = [], deliveryCharge, total }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();

  return (
    <dl className="flex flex-col gap-2.5 text-[15px]">
      <div className="flex justify-between">
        <dt className="text-muted-foreground">{t("subtotal")}</dt>
        <dd className="tabular-nums">{formatTaka(subtotal, locale)}</dd>
      </div>
      {discounts.map((d) => (
        <div key={`${d.type}-${d.label}`} className="flex justify-between gap-4 text-primary">
          <dt>
            {d.type === "coupon"
              ? t("couponDiscount", { code: d.label })
              : d.type === "referral"
                ? t("referralDiscount", { code: d.label })
                : d.type === "points"
                  ? t("pointsDiscount", { points: d.label })
                  : t("bundleDiscount", { name: d.label })}
          </dt>
          <dd className="tabular-nums">−{formatTaka(d.amount, locale)}</dd>
        </div>
      ))}
      <div className="flex justify-between gap-4">
        <dt className="text-muted-foreground">{t("delivery")}</dt>
        <dd className="text-right tabular-nums">
          {deliveryCharge === null ? (
            <span className="text-sm text-muted-foreground">{t("deliveryAtCheckout")}</span>
          ) : deliveryCharge === 0 ? (
            <span className="font-medium text-primary">{t("free")}</span>
          ) : (
            formatTaka(deliveryCharge, locale)
          )}
        </dd>
      </div>
      <div className="mt-1 flex items-baseline justify-between border-t border-border pt-3.5">
        <dt className="font-semibold">{t("total")}</dt>
        <dd className="text-xl font-semibold text-primary tabular-nums">{formatTaka(total, locale)}</dd>
      </div>
    </dl>
  );
}
