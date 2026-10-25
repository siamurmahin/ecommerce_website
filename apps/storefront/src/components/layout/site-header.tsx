import { ChevronDown, ChevronRight, Flame, Heart, LayoutGrid, PackageSearch, Search, UserRound } from "lucide-react";
import { getLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getCategories } from "@/lib/api/catalog";
import { getMenus, getSettings } from "@/lib/api/content";
import { cn } from "@/lib/utils";
import { CartButton } from "./cart-button";
import { BrandMark } from "./brand-mark";
import { CategoryThumb } from "./category-thumb";
import { LocaleSwitch } from "./locale-switch";
import { MenuLink } from "./menu-link";
import { MobileMenu } from "./mobile-menu";
import { SearchForm } from "./search-form";

const iconLink =
  "inline-flex items-center gap-2 rounded-full p-2 text-foreground transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-ring";

export async function SiteHeader() {
  const [t, locale] = [await getTranslations("nav"), await getLocale()];
  const [categories, menus, settings] = await Promise.all([getCategories(locale), getMenus(locale), getSettings(locale)]);

  return (
    <header className="site-chrome sticky top-0 z-30 border-b border-border/70 bg-background/85 backdrop-blur-xl">
      {settings.announcement && (
        <div className="bg-primary text-primary-foreground">
          <p className="mx-auto max-w-6xl px-4 py-1.5 text-center text-xs tracking-wide">
            {settings.announcement}
            <span className="hidden sm:inline">
              {" · "}
              {t("hotline")} <a href={`tel:${settings.hotline_tel}`} className="tabular-nums hover:underline">{settings.hotline}</a>
            </span>
          </p>
        </div>
      )}

      <div className="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 md:h-[72px] md:gap-8">
        <MobileMenu items={menus.header} categories={categories} brand={settings.brand} hotline={settings.hotline} hotlineTel={settings.hotline_tel} className="md:hidden" />
        <Link href="/" aria-label={settings.brand.name} className="shrink-0">
          <BrandMark brand={settings.brand} className="text-2xl" priority />
        </Link>

        <SearchForm className="hidden flex-1 md:block" />

        <nav aria-label={t("account")} className="ml-auto flex items-center gap-1 md:ml-0">
          <Link href="/search" aria-label={t("search")} className={cn(iconLink, "md:hidden")}>
            <Search className="size-[22px]" strokeWidth={1.7} />
          </Link>
          <Link href="/track-order" className={cn(iconLink, "hidden text-sm lg:inline-flex")}>
            <PackageSearch className="size-5" strokeWidth={1.7} />
            {t("trackOrder")}
          </Link>
          <Link href="/wishlist" aria-label={t("wishlist")} className={cn(iconLink, "hidden md:inline-flex")}>
            <Heart className="size-[22px]" strokeWidth={1.7} />
          </Link>
          <Link href="/account" aria-label={t("account")} className={cn(iconLink, "hidden md:inline-flex")}>
            <UserRound className="size-[22px]" strokeWidth={1.7} />
          </Link>
          <CartButton className={iconLink} />
          <span className="hidden md:inline-flex">
            <LocaleSwitch />
          </span>
        </nav>
      </div>

      <nav aria-label={t("mainMenu")} className="relative hidden border-t border-border/70 md:block">
        <div className="mx-auto flex h-12 max-w-6xl items-center gap-2 px-4 text-sm">
          {categories.length > 0 && (
            <div className="group relative self-stretch py-1.5">
              <button
                type="button"
                aria-haspopup="true"
                className="flex h-full items-center gap-2 rounded-md bg-secondary px-4 font-semibold transition-colors group-focus-within:bg-primary group-focus-within:text-primary-foreground group-hover:bg-primary group-hover:text-primary-foreground"
              >
                <LayoutGrid className="size-4" strokeWidth={1.8} />
                {t("allCategories")}
                <ChevronDown className="size-4 transition-transform group-focus-within:rotate-180 group-hover:rotate-180" strokeWidth={1.8} />
              </button>
              <div className="invisible absolute top-full left-0 z-40 w-72 translate-y-1 pt-1 opacity-0 transition-[opacity,transform,visibility] duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                <ul className="flex flex-col rounded-lg border border-border bg-card p-2 shadow-[0_20px_48px_-16px_rgba(23,23,23,0.25)]">
                  {categories.map((c) => {
                    const children = c.children ?? [];

                    return (
                      <li key={c.id} className="group/item relative">
                        <Link
                          href={`/c/${c.slug}`}
                          aria-haspopup={children.length > 0 || undefined}
                          className="flex items-center gap-3 rounded-md px-2.5 py-2 transition-colors group-focus-within/item:bg-secondary group-hover/item:bg-secondary group-hover/item:text-primary"
                        >
                          <CategoryThumb category={c} />
                          <span className="flex-1 truncate font-medium">{c.name}</span>
                          <ChevronRight
                            className={cn("size-4 text-muted-foreground transition-opacity", children.length === 0 && "opacity-0 group-hover/item:opacity-100")}
                            strokeWidth={1.8}
                          />
                        </Link>

                        {children.length > 0 && (
                          // Sits flush against the parent row (pl-2 bridges the gap) so the pointer can travel into it.
                          <div className="invisible absolute top-0 left-full z-40 w-64 -translate-x-1 pl-2 opacity-0 transition-[opacity,transform,visibility] duration-150 group-focus-within/item:visible group-focus-within/item:translate-x-0 group-focus-within/item:opacity-100 group-hover/item:visible group-hover/item:translate-x-0 group-hover/item:opacity-100">
                            <ul className="flex flex-col rounded-lg border border-border bg-card p-2 shadow-[0_20px_48px_-16px_rgba(23,23,23,0.25)]">
                              {children.map((child) => (
                                <li key={child.id}>
                                  <Link href={`/c/${child.slug}`} className="flex items-center gap-3 rounded-md px-2.5 py-2 font-medium transition-colors hover:bg-secondary hover:text-primary">
                                    <CategoryThumb category={child} />
                                    <span className="truncate">{child.name}</span>
                                  </Link>
                                </li>
                              ))}
                              <li className="mt-1 border-t border-border pt-1">
                                <Link href={`/c/${c.slug}`} className="block rounded-md px-2.5 py-2 text-[13px] font-semibold text-primary transition-colors hover:bg-secondary">
                                  {t("viewAllIn", { name: c.name ?? "" })}
                                </Link>
                              </li>
                            </ul>
                          </div>
                        )}
                      </li>
                    );
                  })}
                </ul>
              </div>
            </div>
          )}

          <ul className="flex items-center overflow-x-auto [scrollbar-width:none]">
            {menus.header.map((item) => (
              <li key={item.id} className="flex items-center">
                <MenuLink
                  href={item.url}
                  className={cn(
                    "flex items-center gap-1.5 px-3.5 py-3 font-semibold whitespace-nowrap transition-colors hover:text-primary",
                    item.is_highlighted ? "text-primary" : "text-foreground/80",
                  )}
                >
                  {item.is_highlighted && (
                    <span className="grid size-5 place-items-center rounded-full bg-gold/15">
                      <Flame className="size-3.5 text-gold" strokeWidth={2.2} />
                    </span>
                  )}
                  {item.label}
                </MenuLink>
              </li>
            ))}
          </ul>
        </div>
      </nav>
    </header>
  );
}
