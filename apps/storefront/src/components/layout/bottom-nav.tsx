"use client";

import { motion } from "motion/react";
import { House, LayoutGrid, Search, ShoppingBag, User } from "lucide-react";
import { useTranslations } from "next-intl";
import { Suspense } from "react";
import { Link, usePathname } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { useHydrated } from "@/hooks/use-hydrated";
import { cartCount, useCart } from "@/stores/cart";

const items = [
  { href: "/categories", key: "categories", icon: LayoutGrid },
  { href: "/cart", key: "cart", icon: ShoppingBag },
  { href: "/", key: "home", icon: House, primary: true },
  { href: "/search", key: "search", icon: Search },
  { href: "/account", key: "account", icon: User },
] as const;

/** Mobile-only floating nav with a raised Home button (MASTER_PLAN §5, ref: food.projoss.com). */
export function BottomNav() {
  // The active tab depends on the URL, which isn't known while prerendering dynamic routes.
  return (
    <Suspense fallback={<BottomNavList pathname={null} />}>
      <BottomNavWithPath />
    </Suspense>
  );
}

function BottomNavWithPath() {
  return <BottomNavList pathname={usePathname()} />;
}

function BottomNavList({ pathname }: { pathname: string | null }) {
  const t = useTranslations("nav");
  const count = useCart((s) => cartCount(s.lines));
  const mounted = useHydrated();

  return (
    <nav
      aria-label={t("mobileMenu")}
      className="site-chrome fixed inset-x-0 bottom-0 z-40 px-4 pb-[max(12px,env(safe-area-inset-bottom))] md:hidden"
    >
      <motion.ul
        initial={{ y: 24, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ type: "spring", stiffness: 260, damping: 26 }}
        className="mx-auto grid max-w-md grid-cols-5 items-end rounded-lg border border-border bg-card px-2 pt-2.5 pb-3 shadow-[0_10px_30px_rgba(15,76,58,0.12)]"
      >
        {items.map(({ href, key, icon: Icon, ...rest }) => {
          const active = pathname !== null && (href === "/" ? pathname === "/" : pathname.startsWith(href));
          const primary = "primary" in rest;

          return (
            <li key={href} className="flex justify-center">
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex flex-col items-center gap-1 rounded-lg text-xs text-muted-foreground transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring",
                  active && "font-semibold text-primary",
                )}
              >
                {primary ? (
                  <motion.span
                    whileTap={{ scale: 0.92 }}
                    whileHover={{ y: -3 }}
                    className="-mt-8 grid size-14 place-items-center rounded-full bg-primary text-primary-foreground shadow-[0_0_0_6px_var(--background)]"
                  >
                    <Icon className="size-6" strokeWidth={1.8} />
                  </motion.span>
                ) : (
                  <span className="relative">
                    <Icon className="size-[22px]" strokeWidth={1.7} />
                    {key === "cart" && mounted && count > 0 && (
                      <span className="absolute -top-1.5 -right-2 min-w-4 rounded-full bg-gold px-1 text-center text-[10px] leading-4 font-bold text-[#171717] tabular-nums">
                        {count}
                      </span>
                    )}
                  </span>
                )}
                {t(key)}
              </Link>
            </li>
          );
        })}
      </motion.ul>
    </nav>
  );
}
