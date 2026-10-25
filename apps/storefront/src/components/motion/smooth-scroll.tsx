"use client";

import Lenis from "lenis";
// html.lenis { height: auto } — without it <html class="h-full"> never grows, Lenis keeps the first page's
// height as its scroll limit and scrolling stops part-way down after a client-side navigation.
import "lenis/dist/lenis.css";
import { useEffect } from "react";

/** Lenis smooth scrolling on pointer devices; skipped for touch and reduced motion. */
export function SmoothScroll() {
  useEffect(() => {
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const coarse = window.matchMedia("(pointer: coarse)").matches;
    if (reduce || coarse) return;

    const lenis = new Lenis({
      lerp: 0.12,
      autoRaf: true,
      // Pause while a dialog/drawer locks the page (overflow: hidden) and resume when it closes.
      autoToggle: true,
      // Let wheel events scroll dropdowns and lists that lack data-lenis-prevent.
      allowNestedScroll: true,
      stopInertiaOnNavigate: true,
    });

    return () => lenis.destroy();
  }, []);

  return null;
}
