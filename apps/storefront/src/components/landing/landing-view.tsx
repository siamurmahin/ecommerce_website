"use client";

import { useEffect } from "react";
import { API_URL } from "@/lib/api/client";

/** Counts one view per page per browser session, for the A/B conversion rate in admin. */
export function LandingView({ slug }: { slug: string }) {
  useEffect(() => {
    const key = `lj-lv-${slug}`;
    try {
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, "1");
    } catch {
      // Storage blocked: still count it.
    }
    fetch(`${API_URL}/landing-pages/${encodeURIComponent(slug)}/view`, { method: "POST", keepalive: true, headers: { Accept: "application/json" } }).catch(() => {});
  }, [slug]);

  return null;
}
