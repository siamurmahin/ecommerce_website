"use client";

import { useEffect } from "react";
import type { TrackItem } from "@/lib/tracking";
import { track } from "@/lib/tracking";

/** Fires Purchase once per order on the thank-you page; reloads and revisits don't count again. */
export function TrackPurchase({ number, eventId, total, shipping, items }: { number: string; eventId: string; total: number; shipping: number; items: TrackItem[] }) {
  useEffect(() => {
    const key = `lj-purchase:${number}`;
    try {
      if (localStorage.getItem(key)) return;
      localStorage.setItem(key, "1");
    } catch {
      // Storage blocked: still report it; the shared event_id lets the platforms de-duplicate.
    }
    track("Purchase", { items, eventId, value: total, shipping, transactionId: number });
  }, [number, eventId, total, shipping, items]);

  return null;
}
