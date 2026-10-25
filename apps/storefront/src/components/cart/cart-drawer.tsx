"use client";

import { Dialog } from "@base-ui/react/dialog";
import { ShoppingBag, X } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { FreeDeliveryProgress } from "@/components/commerce/free-delivery-progress";
import { buttonVariants } from "@/components/ui/button";
import { Link } from "@/i18n/navigation";
import { formatNumber, formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";
import { cartCount, cartSubtotal, useCart } from "@/stores/cart";
import { useUi } from "@/stores/ui";
import { CartLine } from "./cart-line";

/** Slide-out mini cart. Prices here are the stored snapshot; cart and checkout re-price on the server. */
export function CartDrawer({ freeDeliveryThreshold }: { freeDeliveryThreshold: number }) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const open = useUi((s) => s.cartOpen);
  const setOpen = useUi((s) => s.setCartOpen);
  const lines = useCart((s) => s.lines);
  const close = () => setOpen(false);
  const subtotal = cartSubtotal(lines);

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-[#171717]/30 transition-opacity duration-300 data-[ending-style]:opacity-0 data-[starting-style]:opacity-0" />
        <Dialog.Popup className="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-background shadow-2xl outline-none transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] data-[ending-style]:translate-x-full data-[starting-style]:translate-x-full sm:rounded-l-lg">
          <div className="flex items-center justify-between border-b border-border px-5 py-4">
            <Dialog.Title className="font-heading text-xl font-semibold">
              {t("title")}
              {lines.length > 0 && (
                <span className="ml-2 font-sans text-sm font-normal text-muted-foreground">{t("items", { count: formatNumber(cartCount(lines), locale) })}</span>
              )}
            </Dialog.Title>
            <Dialog.Close aria-label={t("close")} className="-mr-2 rounded-full p-2 transition-colors hover:bg-secondary">
              <X className="size-5" strokeWidth={1.7} />
            </Dialog.Close>
          </div>

          {lines.length === 0 ? (
            <div className="flex flex-1 flex-col items-center justify-center gap-4 px-8 text-center">
              <span className="grid size-16 place-items-center rounded-full bg-secondary text-primary">
                <ShoppingBag className="size-7" strokeWidth={1.5} />
              </span>
              <div className="flex flex-col gap-1">
                <p className="font-heading text-lg font-semibold">{t("empty")}</p>
                <p className="text-sm text-muted-foreground">{t("emptyNote")}</p>
              </div>
              <Link href="/shop" onClick={close} className={cn(buttonVariants(), "h-11 rounded-full px-6")}>
                {t("startShopping")}
              </Link>
            </div>
          ) : (
            <>
              <FreeDeliveryProgress subtotal={subtotal} threshold={freeDeliveryThreshold} className="border-b border-border px-5 py-4" />
              <ul className="flex-1 divide-y divide-border overflow-y-auto overscroll-contain px-5" data-lenis-prevent>
                {lines.map((line) => (
                  <CartLine key={line.key} line={line} onNavigate={close} compact />
                ))}
              </ul>
              <div className="flex flex-col gap-3 border-t border-border bg-card px-5 pt-4 pb-[max(16px,env(safe-area-inset-bottom))]">
                <div className="flex items-baseline justify-between">
                  <span className="text-muted-foreground">{t("subtotal")}</span>
                  <strong className="text-lg tabular-nums">{formatTaka(subtotal, locale)}</strong>
                </div>
                <p className="-mt-2 text-xs text-muted-foreground">
                  {t("delivery")}: {t("deliveryAtCheckout")}
                </p>
                <Link href="/checkout" onClick={close} className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full text-[15px]")}>
                  {t("checkout")}
                </Link>
                <Link href="/cart" onClick={close} className="text-center text-sm font-medium text-primary underline-offset-4 hover:underline">
                  {t("viewCart")}
                </Link>
              </div>
            </>
          )}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
