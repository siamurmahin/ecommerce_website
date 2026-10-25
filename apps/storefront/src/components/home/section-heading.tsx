import { ArrowRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

type Props = {
  title: string;
  note?: string;
  href?: string;
  linkLabel?: string;
  /** Light text for use on the emerald panel. */
  tone?: "default" | "inverse";
  children?: React.ReactNode;
};

export function SectionHeading({ title, note, href, linkLabel, tone = "default", children }: Props) {
  const inverse = tone === "inverse";

  return (
    <div className={cn("flex items-end justify-between gap-4 border-b pb-4", inverse ? "border-primary-foreground/15" : "border-border")}>
      <div className="flex flex-col gap-1">
        <h2 className={cn("text-2xl font-semibold tracking-tight md:text-[32px]", inverse && "text-primary-foreground")}>{title}</h2>
        {note && <p className={cn("text-sm", inverse ? "text-primary-foreground/75" : "text-muted-foreground")}>{note}</p>}
      </div>
      <div className="flex shrink-0 items-center gap-3">
        {href && linkLabel && (
          <Link href={href} className={cn("group inline-flex items-center gap-1.5 text-sm font-semibold", inverse ? "text-primary-foreground" : "text-primary")}>
            {linkLabel}
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" strokeWidth={2} />
          </Link>
        )}
        {children}
      </div>
    </div>
  );
}
