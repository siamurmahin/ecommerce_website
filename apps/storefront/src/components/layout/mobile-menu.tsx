"use client";

import { Dialog } from "@base-ui/react/dialog";
import { ChevronDown, ChevronRight, Flame, Heart, Menu, PackageSearch, Phone, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Link } from "@/i18n/navigation";
import type { Category, MenuItem, SiteSettings } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { BrandMark } from "./brand-mark";
import { CategoryThumb } from "./category-thumb";
import { LocaleSwitch } from "./locale-switch";
import { MenuLink } from "./menu-link";

type Props = { items: MenuItem[]; categories: Category[]; brand: SiteSettings["brand"]; hotline: string; hotlineTel: string; className?: string };

/** Hamburger + left off-canvas panel: admin menu, categories, quick links. */
export function MobileMenu({ items, categories, brand, hotline, hotlineTel, className }: Props) {
  const t = useTranslations("nav");
  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState<number | null>(null);
  const close = () => setOpen(false);
  const rowClass = "flex items-center gap-3 rounded-md px-3 py-2.5 text-[15px] transition-colors hover:bg-secondary";

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Trigger
        aria-label={t("openMenu")}
        className={cn("-ml-2 inline-flex rounded-full p-2 transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-ring", className)}
      >
        <Menu className="size-6" strokeWidth={1.7} />
      </Dialog.Trigger>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-[#171717]/30 transition-opacity duration-300 data-[ending-style]:opacity-0 data-[starting-style]:opacity-0" />
        <Dialog.Popup className="fixed inset-y-0 left-0 z-50 flex w-[86%] max-w-sm flex-col rounded-r-lg bg-background shadow-2xl outline-none transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] data-[ending-style]:-translate-x-full data-[starting-style]:-translate-x-full">
          <div className="flex items-center justify-between border-b border-border px-5 py-4">
            <Dialog.Title>
              <BrandMark brand={brand} className="text-xl" />
            </Dialog.Title>
            <Dialog.Close aria-label={t("closeMenu")} className="-mr-2 rounded-full p-2 transition-colors hover:bg-secondary">
              <X className="size-5" strokeWidth={1.7} />
            </Dialog.Close>
          </div>

          <div className="flex-1 overflow-y-auto overscroll-contain px-3 py-4" data-lenis-prevent>
            {items.length > 0 && (
              <nav aria-label={t("mainMenu")} className="flex flex-col gap-0.5 border-b border-border pb-4">
                <p className="px-3 pb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">{t("mainMenu")}</p>
                {items.map((item) => (
                  <MenuLink key={item.id} href={item.url} onClick={close} className={cn(rowClass, "font-medium", item.is_highlighted && "text-primary")}>
                    {item.is_highlighted && <Flame className="size-4 text-gold" strokeWidth={2} />}
                    {item.label}
                  </MenuLink>
                ))}
              </nav>
            )}

            {categories.length > 0 && (
              <nav aria-label={t("categories")} className="flex flex-col gap-0.5 pt-4">
                <p className="px-3 pb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">{t("categories")}</p>
                {categories.map((c) => {
                  const children = c.children ?? [];
                  const isOpen = expanded === c.id;

                  if (children.length === 0) {
                    return (
                      <Link key={c.id} href={`/c/${c.slug}`} onClick={close} className={cn(rowClass, "group")}>
                        <CategoryThumb category={c} className="size-10 rounded-md" />
                        <span className="flex-1">{c.name}</span>
                        <ChevronRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" strokeWidth={1.8} />
                      </Link>
                    );
                  }

                  // Rows with sub-categories expand in place; "view all" inside still reaches the parent page.
                  return (
                    <div key={c.id} className={cn("rounded-md transition-colors", isOpen && "bg-secondary")}>
                      <button
                        type="button"
                        aria-expanded={isOpen}
                        aria-controls={`mobile-cat-${c.id}`}
                        onClick={() => setExpanded(isOpen ? null : c.id)}
                        className={cn(rowClass, "w-full text-left", isOpen && "hover:bg-transparent")}
                      >
                        <CategoryThumb category={c} className="size-10 rounded-md" />
                        <span className={cn("flex-1", isOpen && "font-semibold text-primary")}>{c.name}</span>
                        <ChevronDown className={cn("size-4 text-muted-foreground transition-transform duration-200", isOpen && "rotate-180 text-primary")} strokeWidth={1.8} />
                      </button>
                      {isOpen && (
                        <ul id={`mobile-cat-${c.id}`} className="flex animate-in flex-col gap-0.5 pb-2 pl-[52px] pr-2 fade-in-0 duration-200">
                          {children.map((child) => (
                            <li key={child.id}>
                              <Link href={`/c/${child.slug}`} onClick={close} className="block rounded-md px-3 py-2 text-[15px] transition-colors hover:bg-card hover:text-primary">
                                {child.name}
                              </Link>
                            </li>
                          ))}
                          <li>
                            <Link href={`/c/${c.slug}`} onClick={close} className="block rounded-md px-3 py-2 text-sm font-semibold text-primary transition-colors hover:bg-card">
                              {t("viewAllIn", { name: c.name ?? "" })}
                            </Link>
                          </li>
                        </ul>
                      )}
                    </div>
                  );
                })}
              </nav>
            )}
          </div>

          <div className="flex flex-col gap-3 border-t border-border bg-card px-5 pt-4 pb-[max(16px,env(safe-area-inset-bottom))]">
            <div className="grid grid-cols-2 gap-2 text-sm font-medium">
              <Link href="/track-order" onClick={close} className="flex items-center justify-center gap-2 rounded-md border border-border py-2.5 transition-colors hover:border-primary hover:text-primary">
                <PackageSearch className="size-4" strokeWidth={1.8} />
                {t("trackOrder")}
              </Link>
              <Link href="/wishlist" onClick={close} className="flex items-center justify-center gap-2 rounded-md border border-border py-2.5 transition-colors hover:border-primary hover:text-primary">
                <Heart className="size-4" strokeWidth={1.8} />
                {t("wishlist")}
              </Link>
            </div>
            {hotline && (
              <a href={`tel:${hotlineTel}`} className="flex h-11 items-center justify-center gap-2 rounded-md bg-primary text-[15px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">
                <Phone className="size-4" strokeWidth={2} />
                {t("hotline")} {hotline}
              </a>
            )}
            <div className="flex justify-center">
              <LocaleSwitch />
            </div>
          </div>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
