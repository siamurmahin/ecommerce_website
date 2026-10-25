import { useLocale, useTranslations } from "next-intl";
import { formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

type Props = {
  price: number;
  comparePrice?: number | null;
  from?: boolean;
  size?: "sm" | "lg";
  className?: string;
};

export function Price({ price, comparePrice, from, size = "sm", className }: Props) {
  const locale = useLocale();
  const t = useTranslations("product");

  return (
    <div className={cn("flex flex-wrap items-baseline gap-x-2 tabular-nums", className)}>
      <strong className={cn("font-semibold text-primary", size === "lg" ? "text-3xl" : "text-base")}>
        {from && <span className="mr-1 text-xs font-normal text-muted-foreground">{t("from")}</span>}
        {formatTaka(price, locale)}
      </strong>
      {comparePrice ? (
        <s className={cn("text-muted-foreground", size === "lg" ? "text-lg" : "text-sm")}>{formatTaka(comparePrice, locale)}</s>
      ) : null}
    </div>
  );
}
