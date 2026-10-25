import { Check, CircleAlert } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import type { Order, OrderStatus } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const FLOW: OrderStatus[] = ["pending", "confirmed", "processing", "shipped", "delivered"];

/** Delivery progress for the customer. Off-track statuses (on hold, cancelled, returned) show as a notice. */
export function OrderTimeline({ order }: { order: Order }) {
  const t = useTranslations("order");
  const locale = useLocale();
  const history = order.history ?? [];
  // Latest time each status was reached; statuses can repeat if staff moved an order back and forth.
  const reachedAt = new Map(history.map((h) => [h.status, h.at]));
  const current = FLOW.indexOf(order.status);
  const offTrack = current === -1;
  const lastOnTrack = offTrack ? Math.max(...FLOW.map((s, i) => (reachedAt.has(s) ? i : -1))) : current;
  const date = new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "bn-BD", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" });

  return (
    <section className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6">
      <h2 className="font-heading text-xl font-semibold">{t("status")}</h2>

      {offTrack && (
        <p className={cn("flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium", order.status === "on_hold" ? "bg-gold/15 text-foreground" : "bg-destructive/10 text-destructive")}>
          <CircleAlert className="size-4 shrink-0" strokeWidth={2} />
          {t(`statuses.${order.status}`)}
          {reachedAt.has(order.status) && <span className="ml-auto text-xs font-normal opacity-80">{date.format(new Date(reachedAt.get(order.status)!))}</span>}
        </p>
      )}

      <ol className="flex flex-col">
        {FLOW.map((status, i) => {
          const done = i <= lastOnTrack;
          const active = !offTrack && i === current;
          const at = reachedAt.get(status);

          return (
            <li key={status} className="relative flex gap-3 pb-5 last:pb-0">
              {i < FLOW.length - 1 && <span aria-hidden className={cn("absolute top-7 bottom-0 left-[13px] w-0.5", i < lastOnTrack ? "bg-primary" : "bg-border")} />}
              <span
                className={cn(
                  "relative grid size-7 shrink-0 place-items-center rounded-full border-2",
                  done ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card text-muted-foreground",
                  active && "ring-4 ring-primary/15",
                )}
              >
                {done ? <Check className="size-3.5" strokeWidth={3} /> : <span className="size-1.5 rounded-full bg-current" />}
              </span>
              <span className="flex flex-col pt-0.5">
                <span className={cn("text-[15px]", done ? "font-semibold" : "text-muted-foreground")}>{t(`statuses.${status}`)}</span>
                {done && at && <span className="text-xs text-muted-foreground tabular-nums">{date.format(new Date(at))}</span>}
              </span>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
