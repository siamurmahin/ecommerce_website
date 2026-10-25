"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { CornerDownLeft, LayoutGrid, Search, TrendingUp, X } from "lucide-react";
import Image from "next/image";
import { useSearchParams } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState } from "react";
import { getPathname, useRouter } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import type { SearchSuggest } from "@/lib/api/types";
import { formatTaka } from "@/lib/format";
import { cn } from "@/lib/utils";

export const searchInputClass =
  "h-11 w-full rounded-full border border-border bg-card pr-10 pl-10 text-sm outline-none transition-shadow placeholder:text-muted-foreground focus:border-primary focus:shadow-[0_0_0_3px_rgba(15,76,58,0.12)] [&::-webkit-search-cancel-button]:hidden";

type Option = { key: string; href: string };

/** Search with as-you-type suggestions (products, categories, popular searches). */
export function SearchBox({ autoFocus, className }: { autoFocus?: boolean; className?: string }) {
  const t = useTranslations("search");
  const nav = useTranslations("nav");
  const locale = useLocale();
  const router = useRouter();
  const id = useId();
  const current = useSearchParams().get("q") ?? "";
  const [value, setValue] = useState(current);
  const [debounced, setDebounced] = useState(current);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value.trim()), 180);
    return () => clearTimeout(timer);
  }, [value]);

  const { data } = useQuery({
    queryKey: ["suggest", locale, debounced],
    queryFn: () => api<SearchSuggest>(`/search/suggest?q=${encodeURIComponent(debounced)}`, { locale }),
    enabled: open,
    staleTime: 60_000,
    placeholderData: keepPreviousData,
  });

  const typed = debounced.length >= 2;
  const searchHref = (q: string) => `/search?q=${encodeURIComponent(q)}`;
  const options: Option[] = typed
    ? [
        ...(data?.categories ?? []).map((c) => ({ key: `c-${c.slug}`, href: `/c/${c.slug}` })),
        ...(data?.products ?? []).map((p) => ({ key: `p-${p.slug}`, href: `/p/${p.slug}` })),
        { key: "all", href: searchHref(debounced) },
      ]
    : (data?.popular ?? []).map((q) => ({ key: `q-${q}`, href: searchHref(q) }));
  const optionId = (key: string) => `${id}-${key}`;

  const go = (href: string) => {
    setOpen(false);
    inputRef.current?.blur();
    router.push(href);
  };

  const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      setOpen(true);
      if (options.length === 0) return;
      // -1 is the input itself; the list wraps around through it.
      const step = e.key === "ArrowDown" ? 1 : -1;
      const slots = options.length + 1;
      setActive((i) => ((i + 1 + step + slots) % slots) - 1);
    } else if (e.key === "Escape") {
      setOpen(false);
      setActive(-1);
    }
  };

  const activeOption = active >= 0 && active < options.length ? options[active] : null;
  const showPanel = open && (typed ? data !== undefined : options.length > 0);
  // Mousedown on the panel would blur the input (and close it) before the click lands.
  const keepFocus = (e: React.MouseEvent) => e.preventDefault();

  return (
    <form
      role="search"
      action={getPathname({ href: "/search", locale })}
      className={cn("relative", className)}
      onSubmit={(e) => {
        e.preventDefault();
        if (activeOption) go(activeOption.href);
        else if (value.trim()) go(searchHref(value.trim()));
      }}
    >
      <label htmlFor={`${id}-input`} className="sr-only">
        {nav("search")}
      </label>
      <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground" strokeWidth={1.8} />
      <input
        ref={inputRef}
        id={`${id}-input`}
        name="q"
        type="search"
        role="combobox"
        aria-expanded={showPanel}
        aria-controls={`${id}-list`}
        aria-autocomplete="list"
        aria-activedescendant={activeOption ? optionId(activeOption.key) : undefined}
        autoComplete="off"
        autoFocus={autoFocus}
        value={value}
        placeholder={nav("searchPlaceholder")}
        onChange={(e) => {
          setValue(e.target.value);
          setActive(-1);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
        onKeyDown={onKeyDown}
        className={searchInputClass}
      />
      {value && (
        <button
          type="button"
          aria-label={t("clear")}
          onMouseDown={keepFocus}
          onClick={() => {
            setValue("");
            setActive(-1);
            inputRef.current?.focus();
          }}
          className="absolute top-1/2 right-3 grid size-6 -translate-y-1/2 place-items-center rounded-full text-muted-foreground hover:text-foreground"
        >
          <X className="size-4" strokeWidth={1.8} />
        </button>
      )}

      <div
        id={`${id}-list`}
        role="listbox"
        aria-label={t("suggestions")}
        onMouseDown={keepFocus}
        className={cn(
          "absolute inset-x-0 top-full z-40 mt-2 max-h-[70vh] overflow-y-auto rounded-lg border border-border bg-card p-2 shadow-[0_20px_48px_-16px_rgba(23,23,23,0.25)] transition-[opacity,transform] duration-150 motion-reduce:transition-none",
          showPanel ? "visible translate-y-0 opacity-100" : "invisible -translate-y-1 opacity-0",
        )}
      >
        {!typed ? (
          <div className="flex flex-col gap-2 p-2">
            <p className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
              <TrendingUp className="size-3.5" strokeWidth={2} />
              {t("popular")}
            </p>
            <div className="flex flex-wrap gap-2">
              {(data?.popular ?? []).map((q, i) => (
                <button
                  key={q}
                  id={optionId(`q-${q}`)}
                  type="button"
                  role="option"
                  aria-selected={active === i}
                  onClick={() => go(searchHref(q))}
                  className={cn("rounded-full border px-3 py-1.5 text-sm transition-colors hover:border-primary hover:text-primary", active === i ? "border-primary text-primary" : "border-border")}
                >
                  {q}
                </button>
              ))}
            </div>
          </div>
        ) : (
          <div className="flex flex-col">
            {data?.corrected_to && <p className="px-2.5 pt-1 pb-2 text-xs text-muted-foreground">{t("showingFor", { term: data.corrected_to })}</p>}
            {options.length === 1 && <p className="px-2.5 py-6 text-center text-sm text-muted-foreground">{t("empty")}</p>}
            {options.map((option, i) => {
              const rowClass = cn("flex w-full items-center gap-3 rounded-md px-2.5 py-2 text-left text-sm transition-colors hover:bg-secondary", active === i && "bg-secondary");
              const props = { id: optionId(option.key), role: "option" as const, "aria-selected": active === i, type: "button" as const, onClick: () => go(option.href), className: rowClass };

              if (option.key.startsWith("c-")) {
                const category = data!.categories.find((c) => `c-${c.slug}` === option.key)!;
                return (
                  <button key={option.key} {...props}>
                    <span className="grid size-10 shrink-0 place-items-center rounded-md bg-secondary text-primary">
                      <LayoutGrid className="size-4" strokeWidth={1.8} />
                    </span>
                    <span className="flex flex-col">
                      <span className="font-semibold">{category.name}</span>
                      <span className="text-xs text-muted-foreground">{t("category")}</span>
                    </span>
                  </button>
                );
              }
              if (option.key.startsWith("p-")) {
                const product = data!.products.find((p) => `p-${p.slug}` === option.key)!;
                return (
                  <button key={option.key} {...props}>
                    <span className="relative size-10 shrink-0 overflow-hidden rounded-md bg-placeholder">
                      {product.image && <Image src={product.image} alt="" fill sizes="40px" className="object-cover" />}
                    </span>
                    <span className="min-w-0 flex-1 truncate font-medium">{product.name}</span>
                    <span className="shrink-0 font-semibold tabular-nums">{formatTaka(product.price, locale)}</span>
                  </button>
                );
              }
              return (
                <button key={option.key} {...props} className={cn(rowClass, "mt-1 justify-between border-t border-border pt-3 font-semibold text-primary")}>
                  {t("viewAll", { term: debounced })}
                  <CornerDownLeft className="size-4" strokeWidth={1.8} />
                </button>
              );
            })}
          </div>
        )}
      </div>
    </form>
  );
}
