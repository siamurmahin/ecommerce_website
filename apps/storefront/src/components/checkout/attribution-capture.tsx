"use client";

import { useEffect } from "react";
import { captureAttribution } from "@/lib/attribution";
import { useCart } from "@/stores/cart";

export function AttributionCapture() {
  useEffect(() => {
    captureAttribution();
    // A friend's invite link (/?ref=CODE): the code waits in the coupon box, the API decides if it applies.
    const ref = new URLSearchParams(window.location.search).get("ref")?.trim().toUpperCase();
    if (ref && /^[A-Z0-9]{4,12}$/.test(ref) && !useCart.getState().coupon) useCart.getState().setCoupon(ref);
  }, []);
  return null;
}
