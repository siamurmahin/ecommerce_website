"use client";

import { usePathname } from "next/navigation";
import { useEffect } from "react";
import type { TrackingConfig } from "@/lib/api/types";

type PageScript = TrackingConfig["page_scripts"][number];

/** `/p/*` → matches /p/anything; patterns ignore the locale prefix and a trailing slash. */
function matches(pattern: string, path: string): boolean {
  const clean = (p: string) => p.replace(/\/+$/, "") || "/";
  const regex = new RegExp(`^${clean(pattern).split("*").map((s) => s.replace(/[.+?^${}()|[\]\\]/g, "\\$&")).join(".*")}$`);
  return regex.test(clean(path));
}

/**
 * Admin snippets scoped to certain pages (admin → ট্র্যাকিং ও স্ক্রিপ্ট → পেজভিত্তিক স্ক্রিপ্ট).
 * Injected when the visitor reaches a matching page and removed when they leave it.
 */
export function PageScripts({ scripts }: { scripts: PageScript[] }) {
  const pathname = usePathname();

  useEffect(() => {
    const path = pathname.replace(/^\/(bn|en)(?=\/|$)/, "") || "/";
    const added: ChildNode[] = [];

    for (const script of scripts) {
      if (!script.paths.some((p) => matches(p, path))) continue;
      try {
        const fragment = document.createRange().createContextualFragment(script.code);
        added.push(...Array.from(fragment.childNodes));
        (script.placement === "head" ? document.head : document.body).append(fragment);
      } catch {
        // Malformed snippet: skip it rather than break the page.
      }
    }

    return () => added.forEach((node) => node.remove());
  }, [pathname, scripts]);

  return null;
}
