"use client";
import { useCallback, useTransition } from "react";
import { usePathname, useRouter } from "@/i18n/navigation";
import { DEFAULT_FILTERS } from "./constants";
import { filtersToUrlParams } from "./filters";
import type { MarketerFilters } from "./types";

/**
 * URL is the single source of truth for filters/sort/search. Every change
 * does a soft `router.replace`, so the RSC re-fetches the filtered listing
 * while the default (no params) view stays fully server-rendered for SEO.
 */
export function useFilterNavigation(filters: MarketerFilters) {
  const router = useRouter();
  const pathname = usePathname();
  const [isPending, startTransition] = useTransition();

  const apply = useCallback(
    (next: MarketerFilters) => {
      const qs = filtersToUrlParams(next).toString();
      startTransition(() => {
        router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
      });
    },
    [router, pathname],
  );

  const update = useCallback(
    (patch: Partial<MarketerFilters>) => {
      const next = { ...filters, ...patch };
      // "kind" only applies to promoted ads, so reset it when switching to own products.
      if (next.source === "own") next.kind = "all";
      apply(next);
    },
    [filters, apply],
  );

  const toggleIn = useCallback(
    (key: "category" | "brand", id: string) => {
      const list = filters[key];
      update({ [key]: list.includes(id) ? list.filter((x) => x !== id) : [...list, id] });
    },
    [filters, update],
  );

  const clearAll = useCallback(() => apply({ ...DEFAULT_FILTERS, sort: filters.sort }), [filters.sort, apply]);

  return { isPending, update, toggleIn, clearAll };
}
