"use client";

import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

/** Mobile bar that keeps "order now" one tap away, and steps aside once the form is on screen. */
export function StickyOrderBar({ children }: { children: React.ReactNode }) {
  const [hidden, setHidden] = useState(false);

  useEffect(() => {
    const form = document.getElementById("order");
    if (!form) return;
    const observer = new IntersectionObserver(([entry]) => setHidden(entry.isIntersecting), { rootMargin: "0px 0px -20% 0px" });
    observer.observe(form);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      className={cn(
        "fixed inset-x-3 bottom-3 z-30 transition-[opacity,transform] duration-300 md:hidden motion-reduce:transition-none",
        hidden && "pointer-events-none translate-y-4 opacity-0",
      )}
    >
      {children}
    </div>
  );
}
