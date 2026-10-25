import type { PlaceOrderInput } from "@/lib/api/types";

const KEY = "lj-attribution";
const TTCLID_KEY = "lj-ttclid";
const UTM_KEYS = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"] as const;

/** Keep the landing page's UTM tags for the session so the order can be credited to its campaign. */
export function captureAttribution() {
  try {
    const params = new URLSearchParams(window.location.search);
    const utm = Object.fromEntries(UTM_KEYS.flatMap((k) => (params.get(k) ? [[k, params.get(k)!.slice(0, 150)]] : [])));
    if (Object.keys(utm).length > 0) sessionStorage.setItem(KEY, JSON.stringify(utm));
    // TikTok's click id: kept for a week so a later order is still credited to the ad.
    const ttclid = params.get("ttclid");
    if (ttclid) localStorage.setItem(TTCLID_KEY, JSON.stringify({ value: ttclid.slice(0, 255), at: Date.now() }));
  } catch {
    // Storage blocked (private mode); attribution is best-effort.
  }
}

export function readCookie(name: string): string | null {
  const value = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))?.[1];
  return value ? decodeURIComponent(value) : null;
}

/** TikTok browser id (`_ttp`, set by the pixel) and the ad click id, for the Events API. */
export function readTikTokIds(): { ttp: string | null; ttclid: string | null } {
  let ttclid: string | null = null;
  try {
    const saved = JSON.parse(localStorage.getItem(TTCLID_KEY) ?? "null") as { value: string; at: number } | null;
    if (saved && Date.now() - saved.at < 7 * 24 * 60 * 60 * 1000) ttclid = saved.value;
  } catch {
    // Storage blocked; best-effort.
  }
  return { ttp: readCookie("_ttp"), ttclid };
}

export function readAttribution(): Pick<PlaceOrderInput, "utm" | "fbp" | "fbc" | "ttp" | "ttclid"> {
  let utm: PlaceOrderInput["utm"];
  try {
    utm = JSON.parse(sessionStorage.getItem(KEY) ?? "null") ?? undefined;
  } catch {
    utm = undefined;
  }
  return { utm, fbp: readCookie("_fbp"), fbc: readCookie("_fbc"), ...readTikTokIds() };
}
