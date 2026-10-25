"use client";

import { Loader2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { use, useEffect, useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import { Link, useRouter } from "@/i18n/navigation";
import { recoverCart } from "@/lib/api/customer";
import { cn } from "@/lib/utils";
import { useCart } from "@/stores/cart";

/** Read by CheckoutForm on its first render after the reminder link. */
export const RECOVER_KEY = "lj-recover";

export function RecoverCart({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const t = useTranslations("recover");
  const locale = useLocale();
  const router = useRouter();
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    recoverCart(token, locale)
      .then((data) => {
        if (cancelled) return;
        const cart = useCart.getState();
        cart.clear();
        data.lines.forEach((l) =>
          cart.add(
            {
              productId: l.product_id,
              variantId: l.variant_id ?? null,
              slug: l.slug ?? "",
              name: l.name ?? "",
              variantLabel: l.variant_label ?? null,
              image: l.image ?? null,
              price: l.unit_price,
              maxQuantity: Math.max(1, l.available),
            },
            l.quantity,
          ),
        );
        if (data.coupon) cart.setCoupon(data.coupon);
        try {
          sessionStorage.setItem(RECOVER_KEY, JSON.stringify({ name: data.name, phone: data.phone, district_id: data.district_id, thana_id: data.thana_id, address: data.address }));
        } catch {
          // Storage blocked: the cart is back, the customer types the address again.
        }
        router.replace(data.lines.length > 0 ? "/checkout" : "/");
      })
      .catch(() => !cancelled && setFailed(true));
    return () => {
      cancelled = true;
    };
  }, [token, locale, router]);

  if (failed) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-lg bg-card px-6 py-16 text-center">
        <h1 className="font-heading text-2xl font-semibold">{t("expired")}</h1>
        <p className="text-muted-foreground">{t("expiredNote")}</p>
        <Link href="/" className={cn(buttonVariants(), "mt-2 h-11 rounded-full px-6")}>
          {t("shop")}
        </Link>
      </div>
    );
  }

  return (
    <p role="status" className="flex items-center justify-center gap-2 py-16 text-muted-foreground">
      <Loader2 className="size-5 animate-spin" />
      {t("loading")}
    </p>
  );
}
