import { BadgeCheck, Banknote, Clock, Gift, Package, Phone, RotateCcw, ShieldCheck, Star, Truck, type LucideIcon } from "lucide-react";
import type { HomeSection } from "@/lib/api/types";

// Keys match ManageHomepage::TRUST_ICONS (also used by landing page benefits).
export const trustIcons: Record<string, LucideIcon> = {
  truck: Truck,
  banknote: Banknote,
  rotate: RotateCcw,
  shield: ShieldCheck,
  gift: Gift,
  phone: Phone,
  star: Star,
  clock: Clock,
  badge: BadgeCheck,
  package: Package,
};

export function TrustStrip({ items }: { items: HomeSection["items"] }) {
  if (items.length === 0) return null;

  return (
    <section className="mx-auto w-full max-w-6xl px-4">
      <ul className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border lg:grid-flow-col lg:auto-cols-fr lg:grid-cols-none">
        {items.map((item, i) => {
          const Icon = trustIcons[item.icon] ?? Star;
          return (
            <li key={i} className="flex items-center gap-2.5 bg-card p-3 sm:gap-3 sm:p-4 lg:p-5">
              <span className="grid size-9 shrink-0 place-items-center rounded-full bg-secondary text-primary sm:size-10">
                <Icon className="size-5" strokeWidth={1.7} />
              </span>
              <span className="flex min-w-0 flex-col">
                <span className="text-[13px] leading-snug font-semibold sm:text-sm">{item.title}</span>
                {item.note && <span className="text-xs text-muted-foreground">{item.note}</span>}
              </span>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
