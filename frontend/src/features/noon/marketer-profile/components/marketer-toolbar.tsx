"use client";
import { useEffect, useRef, useState } from "react";
import { Search, SlidersHorizontal } from "lucide-react";
import { SEARCH_DEBOUNCE_MS, SORT_OPTIONS } from "../helpers/constants";
import type { ListingSort, MarketerFilters } from "../helpers/types";
import type { useFilterNavigation } from "../helpers/use-filter-navigation";

export interface ToolbarLabels {
  searchPlaceholder: string;
  sortBy: string;
  sortOptions: Record<string, string>;
  openFilter: string;
  results: string;
}

interface Props {
  filters: MarketerFilters;
  nav: Pick<ReturnType<typeof useFilterNavigation>, "update">;
  onOpenFilter: () => void;
  activeCount: number;
  total: number;
  labels: ToolbarLabels;
}

export default function MarketerToolbar({ filters, nav, onOpenFilter, activeCount, total, labels }: Props) {
  const [term, setTerm] = useState(filters.q);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  // Keep the input in sync when the URL changes elsewhere (chip removal, clear all).
  const [prevQ, setPrevQ] = useState(filters.q);
  if (prevQ !== filters.q) {
    setPrevQ(filters.q);
    setTerm(filters.q);
  }
  useEffect(() => () => clearTimeout(timer.current), []);

  const onChange = (value: string) => {
    setTerm(value);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      if (value.trim() !== filters.q) nav.update({ q: value.trim() });
    }, SEARCH_DEBOUNCE_MS);
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="relative">
        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray" />
        <input
          type="search"
          value={term}
          onChange={(e) => onChange(e.target.value)}
          placeholder={labels.searchPlaceholder}
          aria-label={labels.searchPlaceholder}
          className="h-11 w-full rounded-lg border border-border-color bg-white ps-10 pe-3 text-sm outline-none focus:border-main"
        />
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={onOpenFilter}
          className="open-filter inline-flex items-center gap-2 rounded-lg border border-border-color px-4 py-2 text-sm font-semibold text-primary lg:hidden"
        >
          <SlidersHorizontal className="size-4" />
          {labels.openFilter}
          {activeCount > 0 && (
            <span className="rounded-full bg-main px-1.5 text-[10px] font-bold leading-4 text-white">{activeCount}</span>
          )}
        </button>

        <span className="hidden text-sm text-gray lg:inline">{labels.results}</span>

        <label className="ms-auto flex items-center gap-2 text-sm text-primary">
          <span className="text-gray">{labels.sortBy}</span>
          <select
            value={filters.sort}
            onChange={(e) => nav.update({ sort: e.target.value as ListingSort })}
            aria-label={labels.sortBy}
            className="h-10 rounded-lg border border-border-color bg-white px-3 text-sm outline-none focus:border-main"
          >
            {SORT_OPTIONS.map((s) => (
              <option key={s} value={s}>
                {labels.sortOptions[s]}
              </option>
            ))}
          </select>
        </label>
      </div>
      <span className="sr-only">{total}</span>
    </div>
  );
}
