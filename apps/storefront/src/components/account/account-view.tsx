"use client";

import { useQueryClient } from "@tanstack/react-query";
import { Gift, LogOut, MapPin, Package, Star, UserRound } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { useCustomer, useSetCustomer } from "@/hooks/use-customer";
import { useRouter } from "@/i18n/navigation";
import { logout } from "@/lib/api/customer";
import type { CheckoutOptions } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useWishlist } from "@/stores/wishlist";
import { AddressesTab } from "./addresses-tab";
import { LoginForm } from "./login-form";
import { OrdersTab } from "./orders-tab";
import { ProfileTab } from "./profile-tab";
import { ReviewsTab } from "./reviews-tab";
import { RewardsTab } from "./rewards-tab";

const TABS = [
  { key: "orders", icon: Package },
  { key: "addresses", icon: MapPin },
  { key: "reviews", icon: Star },
  { key: "rewards", icon: Gift },
  { key: "profile", icon: UserRound },
] as const;

type Tab = (typeof TABS)[number]["key"];

/** Sign-in when signed out; otherwise orders, address book, reviews to write and profile. */
export function AccountView({ options, next, initialTab }: { options: CheckoutOptions; next: string | null; initialTab: string | null }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const router = useRouter();
  const queryClient = useQueryClient();
  const setCustomer = useSetCustomer();
  const { data: customer, isPending } = useCustomer();
  const [tab, setTab] = useState<Tab>(TABS.some((x) => x.key === initialTab) ? (initialTab as Tab) : "orders");

  if (isPending) return <div className="h-80 animate-pulse rounded-lg bg-card" aria-hidden />;

  if (!customer) {
    return (
      <div className="mx-auto w-full max-w-md rounded-lg bg-card p-6 sm:p-8">
        {/* Only same-site paths are followed after sign-in. */}
        <LoginForm onDone={() => next?.startsWith("/") && !next.startsWith("//") && router.push(next)} />
        <p className="mt-6 border-t border-border pt-5 text-center text-sm text-muted-foreground">{t("loginPerks")}</p>
      </div>
    );
  }

  const signOut = async () => {
    await logout(locale).catch(() => null);
    setCustomer(null);
    // The saved list belongs to the account (still there on next sign-in); don't leave it on a shared device.
    useWishlist.getState().setItems([]);
    queryClient.removeQueries({ queryKey: ["account-orders"] });
    queryClient.removeQueries({ queryKey: ["reviewable"] });
  };

  return (
    <div className="grid items-start gap-6 md:grid-cols-[220px_1fr]">
      <nav aria-label={t("title")} className="flex flex-col gap-1 rounded-lg bg-card p-3 max-md:flex-row max-md:overflow-x-auto max-md:[scrollbar-width:none]">
        <p className="px-3 pt-2 pb-3 font-heading text-lg font-semibold max-md:hidden">{customer.name ? t("welcome", { name: customer.name }) : t("welcomeNoName")}</p>
        {TABS.map(({ key, icon: Icon }) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            aria-current={tab === key ? "page" : undefined}
            className={cn(
              "flex shrink-0 items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors",
              tab === key ? "bg-secondary text-primary" : "text-foreground/80 hover:bg-secondary/60",
            )}
          >
            <Icon className="size-4.5" strokeWidth={1.8} />
            {t(`tabs.${key}`)}
          </button>
        ))}
        <button type="button" onClick={signOut} className="flex shrink-0 items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-secondary/60 md:mt-2 md:border-t md:border-border md:pt-3">
          <LogOut className="size-4.5" strokeWidth={1.8} />
          {t("logout")}
        </button>
      </nav>

      <section className="min-w-0">
        {tab === "orders" && <OrdersTab />}
        {tab === "addresses" && <AddressesTab customer={customer} options={options} />}
        {tab === "reviews" && <ReviewsTab />}
        {tab === "rewards" && <RewardsTab customer={customer} />}
        {tab === "profile" && <ProfileTab customer={customer} />}
      </section>
    </div>
  );
}
