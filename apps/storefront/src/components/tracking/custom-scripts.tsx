"use client";

import { useEffect } from "react";

let injected = false;

/**
 * Admin-provided snippets (TikTok pixel, chat widgets…). Parsed with createContextualFragment so
 * their <script> tags actually run, which markup set via innerHTML would not do.
 */
export function CustomScripts({ head, bodyStart, bodyEnd }: { head: string | null; bodyStart: string | null; bodyEnd: string | null }) {
  useEffect(() => {
    if (injected) return;
    injected = true;

    const insert = (html: string | null, place: (fragment: DocumentFragment) => void) => {
      if (!html?.trim()) return;
      try {
        place(document.createRange().createContextualFragment(html));
      } catch {
        // Malformed snippet: skip it rather than break the page.
      }
    };

    insert(head, (f) => document.head.append(f));
    insert(bodyStart, (f) => document.body.prepend(f));
    insert(bodyEnd, (f) => document.body.append(f));
  }, [head, bodyStart, bodyEnd]);

  return null;
}
