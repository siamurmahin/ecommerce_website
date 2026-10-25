"use client";

import { Trash2 } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { Link } from "@/i18n/navigation";
import type { QuoteLine } from "@/lib/api/types";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { type CartLine as Line, useCart } from "@/stores/cart";

type Props = {
  line: Line;
  /** Server view of this line, when a quote has loaded. */
  quoted?: QuoteLine;
  onNavigate?: () => void;
  compact?: boolean;
};

export function CartLine({ line, quoted, onNavigate, compact }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const setQuantity = useCart((s) => s.setQuantity);
  const remove = useCart((s) => s.remove);

  const problem = quoted?.problem;

  return (
    <li className={cn("flex gap-3", compact ? "py-4" : "py-5 sm:gap-4")}>
      <Link
        href={`/p/${line.slug}`}
        onClick={onNavigate}
        className={cn("relative shrink-0 overflow-hidden rounded-md bg-placeholder", compact ? "size-20" : "size-24 sm:size-28")}
      >
        {line.image && <Image src={line.image} alt="" fill sizes="112px" className="object-cover" />}
      </Link>

      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <Link href={`/p/${line.slug}`} onClick={onNavigate} className="line-clamp-2 text-[15px] leading-snug font-medium hover:text-primary">
              {quoted?.name ?? line.name}
            </Link>
            {line.variantLabel && <p className="text-xs text-muted-foreground">{quoted?.variant_label ?? line.variantLabel}</p>}
          </div>
          <button
            type="button"
            onClick={() => remove(line.key)}
            aria-label={t("remove")}
            className="-m-1.5 rounded-full p-1.5 text-muted-foreground transition-colors hover:text-destructive"
          >
            <Trash2 className="size-4" strokeWidth={1.7} />
          </button>
        </div>

        {problem && (
          <p className="text-xs font-medium text-destructive">
            {problem === "unavailable" ? t("unavailable") : t("onlyLeft", { count: formatNumber(quoted.available, locale) })}
          </p>
        )}

        <div className="mt-auto flex items-center justify-between gap-3 pt-2">
          <QuantityStepper size="sm" value={line.quantity} max={line.maxQuantity} onChange={(q) => setQuantity(line.key, q)} />
          <span className={cn("text-[15px] font-semibold tabular-nums", problem === "unavailable" && "text-muted-foreground line-through")}>
            {formatTaka(line.price * line.quantity, locale)}
          </span>
        </div>
      </div>
    </li>
  );
}
