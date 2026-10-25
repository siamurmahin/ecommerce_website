/**
 * Prayer times computed in the browser from a city's coordinates — no external API.
 * Method: University of Islamic Sciences, Karachi (Fajr/Isha 18°), Hanafi Asr; this is what
 * Islamic Foundation Bangladesh's calendar follows, within a minute or two.
 * Bangladesh is UTC+6 all year (no DST).
 */

/** Keys match the backend's SeasonalSettings::PRAYER_CITIES. */
export const PRAYER_CITIES = {
  dhaka: [23.8103, 90.4125],
  chattogram: [22.3569, 91.7832],
  sylhet: [24.8949, 91.8687],
  rajshahi: [24.3745, 88.6042],
  khulna: [22.8456, 89.5403],
  barishal: [22.701, 90.3535],
  rangpur: [25.7439, 89.2752],
  mymensingh: [24.7471, 90.4203],
  cumilla: [23.4607, 91.1809],
  narayanganj: [23.6238, 90.5],
  gazipur: [23.9999, 90.4203],
  bogura: [24.8465, 89.3773],
  coxs_bazar: [21.4272, 92.0058],
} as const satisfies Record<string, readonly [number, number]>;

export type PrayerCity = keyof typeof PRAYER_CITIES;
export const PRAYERS = ["fajr", "sunrise", "dhuhr", "asr", "maghrib", "isha"] as const;
export type Prayer = (typeof PRAYERS)[number];

const TZ = 6;
const HOUR = 3_600_000;

export const isPrayerCity = (city: string | null | undefined): city is PrayerCity => !!city && city in PRAYER_CITIES;

const rad = (d: number) => (d * Math.PI) / 180;
const deg = (r: number) => (r * 180) / Math.PI;
const fix = (a: number, b: number) => ((a % b) + b) % b;

/** Today's date (y, m 1-12, d) in Bangladesh, whatever the visitor's own timezone. */
export function dhakaDate(now: number): [number, number, number] {
  const d = new Date(now + TZ * HOUR);
  return [d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate()];
}

/** "YYYY-MM-DD" for a Bangladesh date, to compare with the admin's Ramadan dates. */
export const isoDate = ([y, m, d]: [number, number, number]) => `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;

export function addDays([y, m, d]: [number, number, number], days: number): [number, number, number] {
  const t = new Date(Date.UTC(y, m - 1, d + days));
  return [t.getUTCFullYear(), t.getUTCMonth() + 1, t.getUTCDate()];
}

function julian(y: number, m: number, d: number): number {
  if (m <= 2) {
    y -= 1;
    m += 12;
  }
  const a = Math.floor(y / 100);
  return Math.floor(365.25 * (y + 4716)) + Math.floor(30.6001 * (m + 1)) + d + (2 - a + Math.floor(a / 4)) - 1524.5;
}

function sun(jd: number) {
  const D = jd - 2451545;
  const g = fix(357.529 + 0.98560028 * D, 360);
  const q = fix(280.459 + 0.98564736 * D, 360);
  const L = fix(q + 1.915 * Math.sin(rad(g)) + 0.02 * Math.sin(rad(2 * g)), 360);
  const e = 23.439 - 0.00000036 * D;
  const ra = fix(deg(Math.atan2(Math.cos(rad(e)) * Math.sin(rad(L)), Math.cos(rad(L)))) / 15, 24);
  return { declination: deg(Math.asin(Math.sin(rad(e)) * Math.sin(rad(L)))), equation: q / 15 - ra };
}

/** Epoch ms of each prayer on a Bangladesh date, shifted by `offset` minutes. */
export function prayerTimes(city: PrayerCity, date: [number, number, number], offset = 0): Record<Prayer, number> {
  const [lat, lng] = PRAYER_CITIES[city];
  const jd = julian(...date) - lng / 360;

  // Hours (local solar day fraction) → refined with one pass using the sun's position at that time.
  const noon = (t: number) => fix(12 - sun(jd + t / 24).equation, 24);
  const angleTime = (angle: number, t: number, before: boolean) => {
    const { declination } = sun(jd + t / 24);
    const cos = (-Math.sin(rad(angle)) - Math.sin(rad(declination)) * Math.sin(rad(lat))) / (Math.cos(rad(declination)) * Math.cos(rad(lat)));
    const span = deg(Math.acos(Math.max(-1, Math.min(1, cos)))) / 15;
    return noon(t) + (before ? -span : span);
  };
  const asr = (t: number) => {
    const { declination } = sun(jd + t / 24);
    return angleTime(-deg(Math.atan(1 / (2 + Math.tan(rad(Math.abs(lat - declination)))))), t, false);
  };

  const hours: Record<Prayer, number> = {
    fajr: angleTime(18, 5, true),
    sunrise: angleTime(0.833, 6, true),
    dhuhr: noon(12) + 1 / 60, // a minute after zenith
    asr: asr(13),
    maghrib: angleTime(0.833, 18, false) + 1 / 60, // ihtiyat
    isha: angleTime(18, 18, false),
  };

  // Solar hours → UTC: shift by longitude, then minutes are rounded the way calendars print them.
  const midnightUtc = Date.UTC(date[0], date[1] - 1, date[2]);
  return Object.fromEntries(
    PRAYERS.map((p) => [p, Math.round((midnightUtc + (hours[p] - lng / 15) * HOUR) / 60_000 + offset) * 60_000]),
  ) as Record<Prayer, number>;
}
