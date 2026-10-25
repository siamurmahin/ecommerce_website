"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { useHydrated } from "@/hooks/use-hydrated";
import { getMe } from "@/lib/api/customer";
import type { Customer } from "@/lib/api/types";

export const CUSTOMER_KEY = ["customer"] as const;

/** Signed-in customer (null when signed out); undefined while loading. */
export function useCustomer() {
  const locale = useLocale();
  const hydrated = useHydrated();
  const query = useQuery({ queryKey: CUSTOMER_KEY, queryFn: () => getMe(locale), staleTime: 5 * 60_000, retry: false });
  // Prerendered HTML never knows the customer. A boundary that hydrates late can find the answer cached
  // already (the header asked first), so keep reporting "loading" until this component has hydrated.
  return hydrated ? query : { ...query, data: undefined, isPending: true, status: "pending" as const };
}

/** Writes a fresh customer into the cache (after login, profile or address changes). */
export function useSetCustomer() {
  const client = useQueryClient();
  return (customer: Customer | null) => client.setQueryData(CUSTOMER_KEY, customer);
}
