"use client";

import { ShoppingBag } from "lucide-react";
import { useTranslations } from "next-intl";
import { FreeDeliveryProgress } from "@/components/commerce/free-delivery-progress";
import { buttonVariants } from "@/components/ui/button";
import { useCartQuote } from "@/hooks/use-cart-quote";
import { useHydrated } from "@/hooks/use-hydrated";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { cartSubtotal, useCart } from "@/stores/cart";
import { CartLine } from "./cart-line";
import { CouponBox } from "./coupon-box";
import { CrossSells } from "./cross-sells";
import { SummaryRows } from "./summary-rows";

export function CartView({ freeDeliveryThreshold }: { freeDeliveryThreshold: number }) {
  const t = useTranslations("cart");
  const hydrated = useHydrated();
  const lines = useCart((s) => s.lines);
  const { data: quote, isFetching } = useCartQuote();

  if (!hydrated) return <CartViewSkeleton />;

  if (lines.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4 rounded-lg bg-card px-6 py-16 text-center">
        <span className="grid size-16 place-items-center rounded-full bg-secondary text-primary">
          <ShoppingBag className="size-7" strokeWidth={1.5} />
        </span>
        <div className="flex flex-col gap-1">
          <p className="font-heading text-xl font-semibold">{t("empty")}</p>
          <p className="text-sm text-muted-foreground">{t("emptyNote")}</p>
        </div>
        <Link href="/shop" className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
          {t("startShopping")}
        </Link>
      </div>
    );
  }

  const quoted = (productId: number, variantId: number | null) =>
    quote?.lines.find((l) => l.product_id === productId && (l.variant_id ?? null) === variantId);
  const subtotal = quote?.subtotal ?? cartSubtotal(lines);
  const discount = quote?.discount_total ?? 0;
  const blocked = quote?.has_problems ?? false;

  return (
    <div className="flex flex-col gap-14">
    <div className="grid items-start gap-6 lg:grid-cols-[1fr_380px] lg:gap-10">
      <section className="rounded-lg bg-card px-5 sm:px-6">
        <ul className="divide-y divide-border">
          {lines.map((line) => (
            <CartLine key={line.key} line={line} quoted={quoted(line.productId, line.variantId)} />
          ))}
        </ul>
      </section>

      <aside className="flex flex-col gap-5 rounded-lg bg-card p-5 sm:p-6 lg:sticky lg:top-36">
        <h2 className="font-heading text-xl font-semibold">{t("summary")}</h2>
        {!quote?.free_delivery && <FreeDeliveryProgress subtotal={subtotal} threshold={freeDeliveryThreshold} />}
        <SummaryRows subtotal={subtotal} discounts={quote?.discounts} deliveryCharge={quote?.free_delivery ? 0 : null} total={subtotal - discount} />
        <CouponBox quote={quote} loading={isFetching} />
        {blocked && <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{t("fixProblems")}</p>}
        <Link
          href="/checkout"
          aria-disabled={blocked}
          className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full text-[15px]", blocked && "pointer-events-none opacity-50")}
        >
          {t("checkout")}
        </Link>
        <Link href="/shop" className="text-center text-sm font-medium text-primary underline-offset-4 hover:underline">
          {t("continueShopping")}
        </Link>
      </aside>
    </div>
    <CrossSells productIds={lines.map((l) => l.productId)} />
    </div>
  );
}

function CartViewSkeleton() {
  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_380px] lg:gap-10" aria-hidden>
      <div className="flex flex-col gap-5 rounded-lg bg-card p-6">
        {[0, 1].map((i) => (
          <div key={i} className="flex gap-4">
            <div className="size-24 animate-pulse rounded-md bg-placeholder" />
            <div className="flex flex-1 flex-col gap-2 pt-1">
              <div className="h-4 w-2/3 animate-pulse rounded bg-placeholder" />
              <div className="h-3 w-1/4 animate-pulse rounded bg-placeholder" />
            </div>
          </div>
        ))}
      </div>
      <div className="h-64 animate-pulse rounded-lg bg-card" />
    </div>
  );
}
