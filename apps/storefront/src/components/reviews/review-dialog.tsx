"use client";

import { Dialog } from "@base-ui/react/dialog";
import { useQueryClient } from "@tanstack/react-query";
import { ImagePlus, Loader2, Star, X } from "lucide-react";
import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { inputClass } from "@/components/checkout/field";
import { buttonVariants } from "@/components/ui/button";
import { ApiError } from "@/lib/api/client";
import { submitReview } from "@/lib/api/customer";
import { cn } from "@/lib/utils";

type Target = { id: number; name: string | null; image: string | null };
const MAX_PHOTOS = 3;
const MAX_BYTES = 4 * 1024 * 1024;

/** Star rating, text and up to three photos for a product the customer received. */
export function ReviewDialog({ product, open, onOpenChange, onSubmitted }: { product: Target | null; open: boolean; onOpenChange: (open: boolean) => void; onSubmitted?: () => void }) {
  const t = useTranslations("reviews");
  const locale = useLocale();
  const queryClient = useQueryClient();
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState("");
  // Each picked photo keeps its blob preview URL, revoked when removed or the form resets.
  const [photos, setPhotos] = useState<{ file: File; url: string }[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const reset = () => {
    setRating(0);
    setBody("");
    setPhotos((p) => {
      p.forEach((x) => URL.revokeObjectURL(x.url));
      return [];
    });
    setError(null);
    setDone(false);
  };

  const submit = async () => {
    if (!product) return;
    if (rating === 0) return setError(t("errors.rating"));
    setBusy(true);
    setError(null);
    const form = new FormData();
    form.set("product_id", String(product.id));
    form.set("rating", String(rating));
    if (body.trim()) form.set("body", body.trim());
    photos.forEach((p) => form.append("images[]", p.file));
    try {
      await submitReview(form, locale);
      setDone(true);
      onSubmitted?.();
      queryClient.invalidateQueries({ queryKey: ["reviewable"] });
    } catch (e) {
      setError(e instanceof ApiError && e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? t("errors.generic")) : t("errors.generic"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog.Root
      open={open}
      onOpenChange={(next) => {
        onOpenChange(next);
        if (!next) setTimeout(reset, 300);
      }}
    >
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-[#171717]/35 transition-opacity duration-300 data-[ending-style]:opacity-0 data-[starting-style]:opacity-0" />
        <Dialog.Popup
          data-lenis-prevent
          className="fixed inset-x-0 bottom-0 z-50 flex max-h-[92dvh] flex-col gap-5 overflow-y-auto overscroll-contain rounded-t-lg bg-background p-5 shadow-2xl outline-none transition-[transform,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] data-[ending-style]:translate-y-full data-[starting-style]:translate-y-full md:inset-auto md:top-1/2 md:left-1/2 md:w-[min(520px,calc(100vw-48px))] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-lg md:p-7 md:data-[ending-style]:translate-y-[-46%] md:data-[ending-style]:opacity-0 md:data-[starting-style]:translate-y-[-46%] md:data-[starting-style]:opacity-0"
        >
          <Dialog.Close aria-label={t("close")} className="absolute top-3 right-3 rounded-full bg-card p-2 shadow-sm hover:bg-secondary">
            <X className="size-5" strokeWidth={1.7} />
          </Dialog.Close>
          <div className="flex items-center gap-3 pr-10">
            <span className="relative size-14 shrink-0 overflow-hidden rounded-md bg-placeholder">
              {product?.image && <Image src={product.image} alt="" fill sizes="56px" className="object-cover" />}
            </span>
            <span className="flex flex-col">
              <Dialog.Title className="font-heading text-xl font-semibold">{t("formTitle")}</Dialog.Title>
              <span className="text-sm text-muted-foreground">{product?.name}</span>
            </span>
          </div>

          {done ? (
            <p className="rounded-lg bg-secondary px-4 py-6 text-center font-medium text-primary">{t("thanks")}</p>
          ) : (
            <>
              <fieldset className="flex flex-col gap-2">
                <legend className="mb-2 text-sm font-medium">{t("rating")}</legend>
                <div className="flex gap-1">
                  {[1, 2, 3, 4, 5].map((n) => (
                    <button
                      key={n}
                      type="button"
                      onClick={() => setRating(n)}
                      aria-label={t("star", { count: n })}
                      aria-pressed={rating === n}
                      className="rounded-md p-1 transition-transform hover:scale-110 motion-reduce:transition-none"
                    >
                      <Star className={cn("size-8", n <= rating ? "text-gold" : "text-border")} fill="currentColor" strokeWidth={0} />
                    </button>
                  ))}
                </div>
              </fieldset>

              <label className="flex flex-col gap-1.5 text-sm font-medium">
                {t("body")}
                <textarea value={body} onChange={(e) => setBody(e.target.value)} maxLength={1000} rows={4} placeholder={t("bodyPlaceholder")} className={cn(inputClass, "h-auto resize-none py-3 font-normal")} />
              </label>

              <div className="flex flex-col gap-2">
                <span className="text-sm font-medium">{t("photos")}</span>
                <div className="flex flex-wrap gap-2">
                  {photos.map(({ url: src }, i) => (
                    <span key={src} className="relative size-20 overflow-hidden rounded-md bg-placeholder">
                      {/* eslint-disable-next-line @next/next/no-img-element -- local blob preview */}
                      <img src={src} alt="" className="size-full object-cover" />
                      <button type="button" aria-label={t("removePhoto")} onClick={() => {
                          URL.revokeObjectURL(src);
                          setPhotos((p) => p.filter((_, j) => j !== i));
                        }} className="absolute top-1 right-1 rounded-full bg-background/90 p-0.5">
                        <X className="size-3.5" />
                      </button>
                    </span>
                  ))}
                  {photos.length < MAX_PHOTOS && (
                    <label className="grid size-20 cursor-pointer place-items-center rounded-md border-2 border-dashed border-border text-muted-foreground hover:border-primary hover:text-primary">
                      <ImagePlus className="size-6" strokeWidth={1.6} />
                      <span className="sr-only">{t("addPhoto")}</span>
                      <input
                        type="file"
                        accept="image/*"
                        multiple
                        className="sr-only"
                        onChange={(e) => {
                          const files = Array.from(e.target.files ?? []);
                          e.target.value = "";
                          if (files.some((f) => f.size > MAX_BYTES)) return setError(t("errors.photo"));
                          const room = MAX_PHOTOS - photos.length;
                          setPhotos((p) => [...p, ...files.slice(0, room).map((file) => ({ file, url: URL.createObjectURL(file) }))]);
                        }}
                      />
                    </label>
                  )}
                </div>
              </div>

              {error && (
                <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                  {error}
                </p>
              )}

              <button type="button" onClick={submit} disabled={busy} className={cn(buttonVariants({ size: "lg" }), "h-12 rounded-full")}>
                {busy && <Loader2 className="size-4 animate-spin" />}
                {busy ? t("submitting") : t("submit")}
              </button>
            </>
          )}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

