"use client";
import { X } from "lucide-react";
import type { MarketerFacets, MarketerFilters } from "../helpers/types";
import type { useFilterNavigation } from "../helpers/use-filter-navigation";
import type { FilterPanelLabels } from "./marketer-filter-panel";

interface Props {
  filters: MarketerFilters;
  facets: MarketerFacets | null;
  nav: ReturnType<typeof useFilterNavigation>;
  labels: Pick<FilterPanelLabels, "sourceOptions" | "kindOptions" | "discountedOnly" | "priceMin" | "priceMax"> & {
    clearAll: string;
    remove: string;
  };
}

export default function MarketerActiveChips({ filters, facets, nav, labels }: Props) {
  const chips: { key: string; label: string; onRemove: () => void }[] = [];

  if (filters.source !== "all")
    chips.push({ key: "source", label: labels.sourceOptions[filters.source], onRemove: () => nav.update({ source: "all" }) });
  if (filters.kind !== "all")
    chips.push({ key: "kind", label: labels.kindOptions[filters.kind], onRemove: () => nav.update({ kind: "all" }) });
  if (filters.q) chips.push({ key: "q", label: `"${filters.q}"`, onRemove: () => nav.update({ q: "" }) });
  filters.category.forEach((id) =>
    chips.push({
      key: `c-${id}`,
      label: facets?.categories?.find((c) => c.id === id)?.name ?? id,
      onRemove: () => nav.toggleIn("category", id),
    }),
  );
  filters.brand.forEach((id) =>
    chips.push({
      key: `b-${id}`,
      label: facets?.brands?.find((b) => b.id === id)?.name ?? id,
      onRemove: () => nav.toggleIn("brand", id),
    }),
  );
  if (filters.priceMin || filters.priceMax)
    chips.push({
      key: "price",
      label: `${filters.priceMin || "0"} - ${filters.priceMax || "∞"}`,
      onRemove: () => nav.update({ priceMin: "", priceMax: "" }),
    });
  if (filters.discount) chips.push({ key: "discount", label: labels.discountedOnly, onRemove: () => nav.update({ discount: false }) });

  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2">
      {chips.map((c) => (
        <button
          key={c.key}
          type="button"
          onClick={c.onRemove}
          aria-label={`${labels.remove}: ${c.label}`}
          className="inline-flex items-center gap-1 rounded-full border border-border-color bg-gray-2 px-3 py-1 text-xs font-semibold text-primary transition hover:border-main"
        >
          {c.label}
          <X className="size-3" />
        </button>
      ))}
      <button type="button" onClick={nav.clearAll} className="text-xs font-bold text-main underline">
        {labels.clearAll}
      </button>
    </div>
  );
}
