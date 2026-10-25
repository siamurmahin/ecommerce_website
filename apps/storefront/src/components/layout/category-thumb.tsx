import Image from "next/image";
import type { Category } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Small square category image, with the first letter when there's no image yet. */
export function CategoryThumb({ category, className }: { category: Category; className?: string }) {
  return (
    <span className={cn("relative grid size-8 shrink-0 place-items-center overflow-hidden rounded-md bg-placeholder text-xs font-semibold text-muted-foreground", className)}>
      {category.image ? <Image src={category.image} alt="" fill sizes="48px" className="object-cover" /> : (category.name ?? category.slug).slice(0, 1)}
    </span>
  );
}
