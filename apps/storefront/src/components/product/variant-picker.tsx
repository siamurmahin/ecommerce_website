"use client";

import { useMemo, useState } from "react";
import type { Product, ProductVariant } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Option choices → matching variant, with its price, compare price and stock. */
export function useVariantSelection(product: Product) {
  const isVariable = product.options.length > 0;

  // Preselect the first in-stock variant so price and stock are concrete on load.
  const [selected, setSelected] = useState<Record<number, number>>(() => {
    const initial = product.variants.find((v) => v.stock > 0) ?? product.variants[0];
    return Object.fromEntries(
      product.options.map((o) => [o.id, initial?.option_value_ids.find((id) => o.values.some((v) => v.id === id)) ?? o.values[0].id]),
    ) as Record<number, number>;
  });

  const variant: ProductVariant | undefined = useMemo(() => {
    if (!isVariable) return undefined;
    const ids = Object.values(selected);
    return product.variants.find((v) => ids.every((id) => v.option_value_ids.includes(id)));
  }, [isVariable, product.variants, selected]);

  /** Whether choosing `valueId` for `optionId` (keeping the rest) leads to a variant in stock. */
  const available = (optionId: number, valueId: number) => {
    const ids = Object.values({ ...selected, [optionId]: valueId });
    return product.variants.some((v) => v.stock > 0 && ids.every((id) => v.option_value_ids.includes(id)));
  };

  return {
    selected,
    select: (optionId: number, valueId: number) => setSelected((s) => ({ ...s, [optionId]: valueId })),
    available,
    variant,
    price: variant?.price ?? product.price,
    compare: variant ? variant.compare_price : product.compare_price,
    stock: isVariable ? (variant?.stock ?? 0) : product.stock,
  };
}

type Selection = ReturnType<typeof useVariantSelection>;

/** Size chips and colour swatches for each option group. */
export function VariantOptions({ product, selection, className }: { product: Product; selection: Selection; className?: string }) {
  const { selected, select, available } = selection;

  return product.options.map((option) => {
    const current = option.values.find((v) => v.id === selected[option.id]);
    return (
      <fieldset key={option.id} className={cn("flex flex-col gap-3", className)}>
        <legend className="mb-3 text-sm">
          <span className="font-semibold">{option.name}:</span> <span className="text-muted-foreground">{current?.label}</span>
        </legend>
        <div className="flex flex-wrap gap-2">
          {option.values.map((value) => {
            const active = selected[option.id] === value.id;
            const inStock = available(option.id, value.id);
            return option.type === "color" ? (
              <button
                key={value.id}
                type="button"
                onClick={() => select(option.id, value.id)}
                aria-pressed={active}
                aria-label={value.label ?? ""}
                title={value.label ?? ""}
                className={cn(
                  "relative size-10 rounded-full border border-border ring-offset-2 ring-offset-background transition",
                  active && "ring-2 ring-primary",
                  !inStock && "opacity-40",
                )}
                style={{ background: value.color ?? undefined }}
              />
            ) : (
              <button
                key={value.id}
                type="button"
                onClick={() => select(option.id, value.id)}
                aria-pressed={active}
                className={cn(
                  "min-w-12 rounded-full border px-4 py-2 text-sm font-medium transition-colors",
                  active ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card hover:border-primary",
                  !inStock && !active && "text-muted-foreground line-through decoration-1",
                )}
              >
                {value.label}
              </button>
            );
          })}
        </div>
      </fieldset>
    );
  });
}
