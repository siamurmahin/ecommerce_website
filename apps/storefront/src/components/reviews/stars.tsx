import { Star } from "lucide-react";
import { cn } from "@/lib/utils";

/** Read-only star row; `value` may be fractional (rounded to the nearest half for display). */
export function Stars({ value, className, label }: { value: number; className?: string; label?: string }) {
  const rounded = Math.round(value * 2) / 2;
  return (
    <span className={cn("inline-flex items-center gap-0.5 text-gold", className)} role="img" aria-label={label}>
      {[1, 2, 3, 4, 5].map((n) => (
        <span key={n} className="relative inline-block size-[1em]">
          <Star className="absolute inset-0 size-full text-border" fill="currentColor" strokeWidth={0} />
          {rounded >= n - 0.5 && (
            <span className="absolute inset-0 overflow-hidden" style={{ width: rounded >= n ? "100%" : "50%" }}>
              <Star className="size-[1em]" fill="currentColor" strokeWidth={0} />
            </span>
          )}
        </span>
      ))}
    </span>
  );
}
