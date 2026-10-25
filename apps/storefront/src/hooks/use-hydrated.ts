import { useSyncExternalStore } from "react";

const subscribe = () => () => {};

/** False during SSR/prerender, true once running in the browser. */
export function useHydrated(): boolean {
  return useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );
}
