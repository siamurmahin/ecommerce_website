import Image from "next/image";
import type { SiteSettings } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** The uploaded logo, or the brand name with its last word in the primary colour. */
export function BrandMark({ brand, className, priority }: { brand: SiteSettings["brand"]; className?: string; priority?: boolean }) {
  if (brand.logo) {
    return <Image src={brand.logo} alt={brand.name} width={180} height={48} priority={priority} className={cn("h-9 w-auto max-w-[180px] object-contain", className)} />;
  }

  const words = brand.name.trim().split(/\s+/);
  const last = words.length > 1 ? words.pop() : null;

  return (
    <span className={cn("font-heading font-semibold tracking-tight", className)}>
      {words.join(" ")}
      {last && (
        <>
          {" "}
          <span className="text-primary">{last}</span>
        </>
      )}
    </span>
  );
}
