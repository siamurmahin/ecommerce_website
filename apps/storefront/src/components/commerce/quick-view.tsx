"use client";

import { Dialog } from "@base-ui/react/dialog";
import { useQuery } from "@tanstack/react-query";
import { X } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { ProductPurchase } from "@/components/product/product-purchase";
import { api } from "@/lib/api/client";
import type { DeliveryZone, Product } from "@/lib/api/types";
import { useUi } from "@/stores/ui";

/** Product picker in a dialog, opened from cards (variant choice, quick add, order now). */
export function QuickView({ zones }: { zones: DeliveryZone[] }) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const slug = useUi((s) => s.quickView);
  const setQuickView = useUi((s) => s.setQuickView);
  const close = () => setQuickView(null);

  const { data: product, isError } = useQuery({
    queryKey: ["product", locale, slug],
    queryFn: async () => (await api<{ data: Product }>(`/products/${encodeURIComponent(slug!)}`, { locale })).data,
    enabled: slug !== null,
    staleTime: 60_000,
  });

  return (
    <Dialog.Root open={slug !== null} onOpenChange={(open) => !open && close()}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-[#171717]/35 transition-opacity duration-300 data-[ending-style]:opacity-0 data-[starting-style]:opacity-0" />
        <Dialog.Popup className="fixed inset-x-0 bottom-0 z-50 max-h-[92dvh] overflow-y-auto overscroll-contain rounded-t-lg bg-background p-5 shadow-2xl outline-none transition-[transform,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] data-[ending-style]:translate-y-full data-[starting-style]:translate-y-full md:inset-auto md:top-1/2 md:left-1/2 md:w-[min(960px,calc(100vw-48px))] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-lg md:p-8 md:data-[ending-style]:translate-y-[-46%] md:data-[ending-style]:opacity-0 md:data-[starting-style]:translate-y-[-46%] md:data-[starting-style]:opacity-0" data-lenis-prevent>
          <Dialog.Title className="sr-only">{product?.name ?? ""}</Dialog.Title>
          <Dialog.Close aria-label={t("close")} className="absolute top-3 right-3 z-10 rounded-full bg-card p-2 shadow-sm transition-colors hover:bg-secondary">
            <X className="size-5" strokeWidth={1.7} />
          </Dialog.Close>
          {product && product.slug === slug ? (
            <ProductPurchase key={product.id} product={product} zones={zones} compact onDone={close} />
          ) : isError ? null : (
            <div className="grid gap-6 md:grid-cols-2" aria-hidden>
              <div className="aspect-[4/5] animate-pulse rounded-lg bg-placeholder" />
              <div className="flex flex-col gap-3 pt-4">
                <div className="h-8 w-3/4 animate-pulse rounded bg-placeholder" />
                <div className="h-6 w-1/3 animate-pulse rounded bg-placeholder" />
                <div className="mt-6 h-12 animate-pulse rounded-full bg-placeholder" />
              </div>
            </div>
          )}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
