import { API_URL } from "@/lib/api/client";
import type { TrackEventInput } from "@/lib/api/types";
import { readCookie, readTikTokIds } from "@/lib/attribution";

/**
 * Standard ecommerce events, fired to every configured browser tag (GTM dataLayer, GA4 gtag,
 * Meta Pixel, TikTok Pixel, Google Ads) and mirrored to the backend for Meta CAPI / TikTok Events API. Browser and server share one
 * `event_id`, so each platform counts the event once. Money in is poisha; tags get taka.
 */
export type TrackEvent = "ViewContent" | "AddToCart" | "InitiateCheckout" | "Purchase";

export type TrackItem = {
  productId: number;
  variantId?: number | null;
  name: string;
  variant?: string | null;
  category?: string | null;
  price: number;
  quantity: number;
};

type TrackOptions = {
  items: TrackItem[];
  eventId?: string;
  /** Defaults to the items' total. */
  value?: number;
  transactionId?: string;
  shipping?: number;
};

type Gtag = (...args: unknown[]) => void;

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: Gtag;
    fbq?: (...args: unknown[]) => void;
    ttq?: { track: (...args: unknown[]) => void; page: () => void };
  }
}

const GA4_NAMES: Record<TrackEvent, string> = {
  ViewContent: "view_item",
  AddToCart: "add_to_cart",
  InitiateCheckout: "begin_checkout",
  Purchase: "purchase",
};

const TIKTOK_NAMES: Record<TrackEvent, string> = {
  ViewContent: "ViewContent",
  AddToCart: "AddToCart",
  InitiateCheckout: "InitiateCheckout",
  Purchase: "CompletePayment",
};

export type TrackingSetup = {
  gtmId: string | null;
  ga4Id: string | null;
  pixelId: string | null;
  tiktokId: string | null;
  adsId: string | null;
  adsPurchaseLabel: string | null;
  serverEvents: boolean;
};

const config: TrackingSetup = { gtmId: null, ga4Id: null, pixelId: null, tiktokId: null, adsId: null, adsPurchaseLabel: null, serverEvents: false };
let initialized = false;

/**
 * Sets up each tag's command queue synchronously (the vendor snippets minus their script
 * injection; the libraries load later via next/script and drain the queues). Runs during the
 * first client render, so events fired from any effect are queued rather than lost.
 */
export function configureTracking(next: TrackingSetup) {
  if (initialized || typeof window === "undefined") return;
  initialized = true;
  Object.assign(config, next);

  window.dataLayer ??= [];
  if (config.gtmId) window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });

  if (config.ga4Id || config.adsId) {
    window.gtag = function gtag() {
      // gtag.js reads the Arguments object itself, not an array.
      // eslint-disable-next-line prefer-rest-params
      window.dataLayer!.push(arguments);
    };
    window.gtag("js", new Date());
    if (config.ga4Id) window.gtag("config", config.ga4Id);
    if (config.adsId) window.gtag("config", config.adsId);
  }

  if (config.tiktokId && !window.ttq) {
    // TikTok's base snippet, minus the script tag (loaded by TrackingScripts): calls queue until events.js arrives.
    type Ttq = unknown[] & Record<string, unknown> & { methods: string[]; setAndDefer: (t: Ttq, e: string) => void };
    const ttq = [] as unknown as Ttq;
    ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie", "holdConsent", "revokeConsent", "grantConsent"];
    ttq.setAndDefer = (t, e) => {
      t[e] = (...args: unknown[]) => t.push([e, ...args]);
    };
    ttq.methods.forEach((m) => ttq.setAndDefer(ttq, m));
    (window as unknown as { TiktokAnalyticsObject: string }).TiktokAnalyticsObject = "ttq";
    window.ttq = ttq as unknown as Window["ttq"];
    ttq._i = { [config.tiktokId]: [] };
    ttq._t = { [config.tiktokId]: Date.now() };
    ttq._o = { [config.tiktokId]: {} };
    window.ttq!.page();
  }

  if (config.pixelId && !window.fbq) {
    type Fbq = ((...args: unknown[]) => void) & { callMethod?: (...args: unknown[]) => void; queue: unknown[]; push: unknown; loaded: boolean; version: string };
    const fbq = function (...args: unknown[]) {
      if (fbq.callMethod) fbq.callMethod(...args);
      else fbq.queue.push(args);
    } as Fbq;
    fbq.queue = [];
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = "2.0";
    window.fbq = fbq;
    (window as unknown as { _fbq: Fbq })._fbq = fbq;
    fbq("init", config.pixelId);
    fbq("track", "PageView");
  }
}

const taka = (poisha: number) => Math.round(poisha) / 100;

/** Returns the event id used, so callers can reuse it (e.g. for the order). */
export function track(event: TrackEvent, { items, eventId = crypto.randomUUID(), value, transactionId, shipping }: TrackOptions): string {
  const total = taka(value ?? items.reduce((sum, i) => sum + i.price * i.quantity, 0));

  try {
    const ecommerce = {
      currency: "BDT",
      value: total,
      ...(transactionId ? { transaction_id: transactionId } : {}),
      ...(shipping !== undefined ? { shipping: taka(shipping) } : {}),
      items: items.map((i) => ({
        item_id: String(i.productId),
        item_name: i.name,
        ...(i.variant ? { item_variant: i.variant } : {}),
        ...(i.category ? { item_category: i.category } : {}),
        price: taka(i.price),
        quantity: i.quantity,
      })),
    };

    // GA4 ecommerce schema for GTM; clear the previous ecommerce object first, as Google recommends.
    window.dataLayer ??= [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event: GA4_NAMES[event], event_id: eventId, ecommerce });

    if (config.ga4Id && window.gtag) window.gtag("event", GA4_NAMES[event], { ...ecommerce, event_id: eventId });

    window.fbq?.(
      "track",
      event,
      {
        currency: "BDT",
        value: total,
        content_type: "product",
        content_ids: items.map((i) => String(i.productId)),
        contents: items.map((i) => ({ id: String(i.productId), quantity: i.quantity, item_price: taka(i.price) })),
        num_items: items.reduce((sum, i) => sum + i.quantity, 0),
        ...(transactionId ? { order_id: transactionId } : {}),
      },
      { eventID: eventId },
    );

    window.ttq?.track(
      TIKTOK_NAMES[event],
      {
        currency: "BDT",
        value: total,
        content_type: "product",
        contents: items.map((i) => ({ content_id: String(i.productId), content_name: i.name, quantity: i.quantity, price: taka(i.price) })),
        ...(transactionId ? { order_id: transactionId } : {}),
      },
      { event_id: eventId },
    );

    if (event === "Purchase" && config.adsId && config.adsPurchaseLabel && window.gtag) {
      window.gtag("event", "conversion", { send_to: `${config.adsId}/${config.adsPurchaseLabel}`, value: total, currency: "BDT", transaction_id: transactionId ?? eventId });
    }
  } catch {
    // A broken third-party tag must never break the shop.
  }

  // Purchase is sent server-side by the order itself.
  if (config.serverEvents && event !== "Purchase") {
    const body: TrackEventInput = {
      name: event,
      event_id: eventId,
      url: window.location.href,
      items: items.map((i) => ({ product_id: i.productId, variant_id: i.variantId ?? null, quantity: i.quantity })),
      fbp: readCookie("_fbp"),
      fbc: readCookie("_fbc"),
      ...readTikTokIds(),
    };
    fetch(`${API_URL}/tracking/events`, {
      method: "POST",
      keepalive: true,
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    }).catch(() => {});
  }

  return eventId;
}

/** GA4 browser ids from the `_ga` / `_ga_<id>` cookies, so the server-side purchase joins the session. */
export function readGaIds(): { ga_client_id: string | null; ga_session_id: string | null } {
  const clientId = readCookie("_ga")?.match(/^GA\d\.\d\.(.+)$/)?.[1] ?? null;
  const stream = config.ga4Id ? readCookie(`_ga_${config.ga4Id.replace(/^G-/, "")}`) : null;
  // GS1.1.<session>.… or GS2.1.s<session>$o1$…
  const sessionId = stream?.match(/^GS\d\.\d\.s?(\d+)/)?.[1] ?? null;
  return { ga_client_id: clientId, ga_session_id: sessionId };
}
