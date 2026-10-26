"use client";

import { useLocale, useTranslations } from "next-intl";
import { useSyncExternalStore } from "react";

const noop = () => () => {};

/** Today's Hijri date (Umm al-Qura), shifted by the admin's offset to match local moon sighting. Client-only: it depends on the visitor's day. */
export function HijriDate({ offset }: { offset: number }) {
  const locale = useLocale();
  const suffix = useTranslations("nav")("hijriSuffix");
  const label = useSyncExternalStore(
    noop,
    () => {
      const day = new Date(Date.now() + offset * 86_400_000);
      const parts = new Intl.DateTimeFormat(`${locale === "bn" ? "bn-BD" : "en-GB"}-u-ca-islamic-umalqura`, {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "Asia/Dhaka",
      }).formatToParts(day);
      const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? "";
      // Built by hand: Bangla's era label ("যুগ") reads oddly, so the suffix comes from the messages.
      return `${part("day")} ${part("month")} ${part("year")} ${suffix}`;
    },
    () => null,
  );

  return label ? <span className="tabular-nums">{label}</span> : null;
}
