"use client";

import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState, useSyncExternalStore } from "react";
import { selectClass, selectStyle } from "@/components/checkout/field";
import { Reveal } from "@/components/motion/reveal";
import { formatNumber } from "@/lib/format";
import { addDays, dhakaDate, isoDate, isPrayerCity, PRAYER_CITIES, PRAYERS, prayerTimes, type Prayer, type PrayerCity } from "@/lib/prayer-times";
import { cn } from "@/lib/utils";

const CITY_KEY = "lj_prayer_city";
const ASK = ["fajr", "dhuhr", "asr", "maghrib", "isha"] as const satisfies Prayer[];

// The visitor's chosen city, shared across tabs; null on the server so the markup matches.
const cityListeners = new Set<() => void>();
const subscribeCity = (cb: () => void) => {
  cityListeners.add(cb);
  window.addEventListener("storage", cb);
  return () => {
    cityListeners.delete(cb);
    window.removeEventListener("storage", cb);
  };
};
const readCity = () => {
  try {
    return localStorage.getItem(CITY_KEY);
  } catch {
    return null;
  }
};
function saveCity(city: PrayerCity) {
  try {
    localStorage.setItem(CITY_KEY, city);
  } catch {}
  cityListeners.forEach((cb) => cb());
}

const pad = (n: number, locale: string) => formatNumber(n, locale).padStart(2, locale === "bn" ? "০" : "0");

function countdown(ms: number, locale: string) {
  const s = Math.max(0, Math.floor(ms / 1000));
  return [Math.floor(s / 3600), Math.floor((s % 3600) / 60), s % 60].map((n) => pad(n, locale)).join(":");
}

type Props = {
  title: string | null;
  defaultCity: string | null;
  offset: number;
  ramadan: { starts_on: string | null; ends_on: string | null };
};

/** Today's prayer times for a Bangladeshi city, with Sehri/Iftar countdowns during Ramadan. */
export function PrayerTimes({ title, defaultCity, offset, ramadan }: Props) {
  const t = useTranslations("prayer");
  const locale = useLocale();
  const stored = useSyncExternalStore(subscribeCity, readCity, () => null);
  const city: PrayerCity = isPrayerCity(stored) ? stored : isPrayerCity(defaultCity) ? defaultCity : "dhaka";
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    const tick = () => setNow(Date.now());
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, []);

  const time = new Intl.DateTimeFormat(locale === "en" ? "en-BD" : "bn-BD", { hour: "numeric", minute: "2-digit", timeZone: "Asia/Dhaka" });
  const today = now === null ? null : dhakaDate(now);
  const times = today ? prayerTimes(city, today, offset) : null;
  const tomorrow = today ? prayerTimes(city, addDays(today, 1), offset) : null;
  const next = times && now !== null ? ASK.find((p) => times[p] > now) : undefined;

  const inRamadan = (d: [number, number, number]) => {
    const iso = isoDate(d);
    return !!ramadan.starts_on && !!ramadan.ends_on && iso >= ramadan.starts_on && iso <= ramadan.ends_on;
  };
  // Before Fajr: Sehri ends today. Daytime: Iftar today. After Maghrib: tomorrow's Sehri, if still Ramadan.
  let fast: { label: "sehriEnds" | "iftarIn"; at: number } | null = null;
  if (today && times && tomorrow && now !== null) {
    if (inRamadan(today) && now < times.fajr) fast = { label: "sehriEnds", at: times.fajr };
    else if (inRamadan(today) && now < times.maghrib) fast = { label: "iftarIn", at: times.maghrib };
    else if (inRamadan(addDays(today, 1))) fast = { label: "sehriEnds", at: tomorrow.fajr };
  }

  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <Reveal className="flex flex-col gap-5 rounded-lg bg-card p-5 md:p-8">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div className="flex flex-col gap-1">
            <h2 className="text-2xl font-semibold tracking-tight md:text-[32px]">{title ?? t("title")}</h2>
            <p className="text-sm text-muted-foreground">{t("note")}</p>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">{t("city")}</span>
            <select
              value={city}
              onChange={(e) => isPrayerCity(e.target.value) && saveCity(e.target.value)}
              className={`${selectClass} h-10! w-44!`}
              style={selectStyle}
            >
              {(Object.keys(PRAYER_CITIES) as PrayerCity[]).map((c) => (
                <option key={c} value={c}>
                  {t(`cities.${c}`)}
                </option>
              ))}
            </select>
          </label>
        </div>

        {fast && now !== null && times && (
          <div className="flex flex-col gap-3 rounded-md bg-primary p-4 text-primary-foreground sm:flex-row sm:items-center sm:justify-between md:px-6">
            <div className="flex gap-6 text-sm">
              <span>
                {t("sehri")} <strong className="tabular-nums">{time.format(fast.label === "sehriEnds" ? fast.at : times.fajr)}</strong>
              </span>
              <span>
                {t("iftar")} <strong className="tabular-nums">{time.format(fast.label === "iftarIn" ? fast.at : times.maghrib)}</strong>
              </span>
            </div>
            <p className="flex items-baseline gap-2" role="timer" aria-live="off">
              <span className="text-sm">{t(fast.label)}</span>
              <strong className="font-sans text-2xl font-bold tabular-nums">{countdown(fast.at - now, locale)}</strong>
            </p>
          </div>
        )}

        <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6 sm:gap-3">
          {PRAYERS.map((p) => (
            <li
              key={p}
              aria-current={p === next || undefined}
              className={cn(
                "flex flex-col items-center gap-1 rounded-md border px-2 py-3 text-center transition-colors",
                p === next ? "border-primary bg-primary/5" : "border-border",
                p === "sunrise" && "text-muted-foreground",
              )}
            >
              <span className={cn("text-xs font-semibold", p === next ? "text-primary" : "text-muted-foreground")}>{t(p)}</span>
              <span className="min-h-6 text-base font-bold tabular-nums">{times ? time.format(times[p]) : ""}</span>
              {p === next && now !== null && times && <span className="text-[11px] text-primary tabular-nums">{countdown(times[p] - now, locale)}</span>}
            </li>
          ))}
        </ul>
      </Reveal>
    </section>
  );
}
