"use client";
import { useEffect, useState } from "react";
import { X } from "lucide-react";
import { countActiveFilters } from "../helpers/filters";
import { useFilterNavigation } from "../helpers/use-filter-navigation";
import type { MarketerFacets, MarketerFilters, MarketerListings } from "../helpers/types";
import MarketerActiveChips from "./marketer-active-chips";
import MarketerFilterPanel, { type FilterPanelLabels } from "./marketer-filter-panel";
import MarketerListingsGrid from "./marketer-listings-grid";
import MarketerToolbar, { type ToolbarLabels } from "./marketer-toolbar";

interface Props {
  slug: string;
  filters: MarketerFilters;
  listings: MarketerListings;
  facets: MarketerFacets | null;
  /** Server-rendered banners carousel, slotted above the toolbar. */
  children?: React.ReactNode;
  labels: {
    panel: FilterPanelLabels;
    toolbar: Omit<ToolbarLabels, "results">;
    resultsCount: string;
    filtersTitle: string;
    closeFilter: string;
    clearAll: string;
    removeFilter: string;
    grid: React.ComponentProps<typeof MarketerListingsGrid>["labels"];
  };
}

export default function MarketerCatalog({ slug, filters, listings, facets, children, labels }: Props) {
  const nav = useFilterNavigation(filters);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const activeCount = countActiveFilters(filters);

  useEffect(() => {
    if (!drawerOpen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setDrawerOpen(false);
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener("keydown", onKey);
    };
  }, [drawerOpen]);

  const panel = facets ? <MarketerFilterPanel facets={facets} filters={filters} nav={nav} labels={labels.panel} /> : null;
  // Remount grid (reset load-more state) whenever the server sends a new filtered page.
  const gridKey = JSON.stringify(filters);

  return (
    <div className="flex flex-col gap-6 lg:flex-row">
      {/* First in DOM = right in RTL, left in LTR (boutiqaat puts the facets on the end of the reading flow). */}
      {panel && (
        <aside className="hidden w-64 shrink-0 lg:block" aria-label={labels.filtersTitle}>
          {panel}
        </aside>
      )}

      {drawerOpen && panel && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label={labels.filtersTitle}>
          <button
            type="button"
            aria-label={labels.closeFilter}
            className="absolute inset-0 bg-black/50"
            onClick={() => setDrawerOpen(false)}
          />
          <div className="absolute inset-y-0 flex w-[85%] max-w-sm flex-col bg-white shadow-xl ltr:left-0 rtl:right-0">
            <div className="flex items-center justify-between border-b border-border-color px-4 py-3">
              <h3 className="font-bold text-primary">{labels.filtersTitle}</h3>
              <button type="button" onClick={() => setDrawerOpen(false)} aria-label={labels.closeFilter} className="rounded-full p-1 hover:bg-gray-2">
                <X className="size-5" />
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-4">{panel}</div>
            <div className="flex gap-2 border-t border-border-color p-3">
              {activeCount > 0 && (
                <button type="button" onClick={nav.clearAll} className="flex-1 rounded-lg border border-border-color py-2.5 text-sm font-semibold">
                  {labels.clearAll}
                </button>
              )}
              <button type="button" onClick={() => setDrawerOpen(false)} className="flex-1 rounded-lg bg-primary py-2.5 text-sm font-bold text-white">
                {labels.resultsCount}
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="min-w-0 flex-1 space-y-4">
        {children}

        <MarketerToolbar
          filters={filters}
          nav={nav}
          onOpenFilter={() => setDrawerOpen(true)}
          activeCount={activeCount}
          total={listings.meta.total}
          labels={{ ...labels.toolbar, results: labels.resultsCount }}
        />

        <MarketerActiveChips
          filters={filters}
          facets={facets}
          nav={nav}
          labels={{
            sourceOptions: labels.panel.sourceOptions,
            kindOptions: labels.panel.kindOptions,
            discountedOnly: labels.panel.discountedOnly,
            priceMin: labels.panel.priceMin,
            priceMax: labels.panel.priceMax,
            clearAll: labels.clearAll,
            remove: labels.removeFilter,
          }}
        />

        <div className={nav.isPending ? "pointer-events-none opacity-50 transition-opacity" : "transition-opacity"}>
          <MarketerListingsGrid key={gridKey} slug={slug} filters={filters} initial={listings} labels={labels.grid} />
        </div>
      </div>
    </div>
  );
}
