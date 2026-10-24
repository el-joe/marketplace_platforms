"use client";
import { useState } from "react";
import { FACET_COLLAPSED_ROWS, KIND_OPTIONS, SOURCE_OPTIONS } from "../helpers/constants";
import type { FacetOption, MarketerFacets, MarketerFilters } from "../helpers/types";
import type { useFilterNavigation } from "../helpers/use-filter-navigation";

export interface FilterPanelLabels {
  source: string;
  sourceOptions: Record<string, string>;
  kind: string;
  kindOptions: Record<string, string>;
  category: string;
  brand: string;
  price: string;
  priceMin: string;
  priceMax: string;
  applyPrice: string;
  discounts: string;
  discountedOnly: string;
  showMore: string;
  showLess: string;
  searchFacet: string;
}

interface Props {
  facets: MarketerFacets;
  filters: MarketerFilters;
  nav: Pick<ReturnType<typeof useFilterNavigation>, "update" | "toggleIn">;
  labels: FilterPanelLabels;
}

function Group({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="border-b border-border-color py-5 first:pt-0">
      <h4 className="mb-3 text-sm font-bold text-primary">{title}</h4>
      {children}
    </section>
  );
}

function Check({ checked, onChange, label, count }: { checked: boolean; onChange: () => void; label: string; count?: number }) {
  return (
    <label className="flex cursor-pointer items-center gap-2 py-1 text-sm text-primary">
      <input type="checkbox" checked={checked} onChange={onChange} className="size-4 accent-main" />
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {count != null && <span className="text-xs text-gray">{count}</span>}
    </label>
  );
}

function Radio({ checked, onChange, label, count }: { checked: boolean; onChange: () => void; label: string; count?: number }) {
  return (
    <label className="flex cursor-pointer items-center gap-2 py-1 text-sm text-primary">
      <input type="radio" checked={checked} onChange={onChange} className="size-4 accent-main" />
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {count != null && <span className="text-xs text-gray">{count}</span>}
    </label>
  );
}

function OptionList({
  options, selected, onToggle, labels,
}: {
  options: FacetOption[];
  selected: string[];
  onToggle: (id: string) => void;
  labels: Pick<FilterPanelLabels, "showMore" | "showLess" | "searchFacet">;
}) {
  const [term, setTerm] = useState("");
  const [expanded, setExpanded] = useState(false);
  const filtered = term ? options.filter((o) => o.name.toLowerCase().includes(term.toLowerCase())) : options;
  const visible = expanded || term ? filtered : filtered.slice(0, FACET_COLLAPSED_ROWS);

  return (
    <div>
      {options.length > FACET_COLLAPSED_ROWS && (
        <input
          type="search"
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          placeholder={labels.searchFacet}
          className="mb-2 w-full rounded border border-border-color px-2 py-1.5 text-sm outline-none focus:border-main"
        />
      )}
      <div className="max-h-72 overflow-y-auto pe-1">
        {visible.map((o) => (
          <Check key={o.id} checked={selected.includes(o.id)} onChange={() => onToggle(o.id)} label={o.name} count={o.count} />
        ))}
      </div>
      {!term && filtered.length > FACET_COLLAPSED_ROWS && (
        <button type="button" onClick={() => setExpanded((v) => !v)} className="mt-1 text-xs font-semibold text-main">
          {expanded ? labels.showLess : labels.showMore}
        </button>
      )}
    </div>
  );
}

export default function MarketerFilterPanel({ facets, filters, nav, labels }: Props) {
  const [min, setMin] = useState(filters.priceMin);
  const [max, setMax] = useState(filters.priceMax);

  const sources = SOURCE_OPTIONS.map((key) => ({
    key,
    count: key === "all" ? facets.sources?.reduce((m, s) => Math.max(m, s.count), 0) : facets.sources?.find((s) => s.key === key)?.count,
  })).filter((s) => s.key === "all" || s.count == null || s.count > 0);

  const classifiedCount = facets.kinds?.find((k) => k.key === "classified")?.count ?? 0;
  const showKind = filters.source !== "own" && classifiedCount > 0;
  const showSource = (facets.sources?.length ?? 0) > 0;

  return (
    <div>
      {showSource && (
        <Group title={labels.source}>
          {sources.map((s) => (
            <Radio
              key={s.key}
              checked={filters.source === s.key}
              onChange={() => nav.update({ source: s.key })}
              label={labels.sourceOptions[s.key]}
              count={s.count}
            />
          ))}
        </Group>
      )}

      {showKind && (
        <Group title={labels.kind}>
          {KIND_OPTIONS.map((key) => (
            <Radio
              key={key}
              checked={filters.kind === key}
              onChange={() => nav.update({ kind: key })}
              label={labels.kindOptions[key]}
              count={key === "all" ? undefined : facets.kinds?.find((k) => k.key === key)?.count}
            />
          ))}
        </Group>
      )}

      {!!facets.categories?.length && (
        <Group title={labels.category}>
          <OptionList options={facets.categories} selected={filters.category} onToggle={(id) => nav.toggleIn("category", id)} labels={labels} />
        </Group>
      )}

      {!!facets.brands?.length && (
        <Group title={labels.brand}>
          <OptionList options={facets.brands} selected={filters.brand} onToggle={(id) => nav.toggleIn("brand", id)} labels={labels} />
        </Group>
      )}

      {facets.price && facets.price.max > facets.price.min && (
        <Group title={labels.price}>
          <form
            className="flex items-center gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              nav.update({ priceMin: min, priceMax: max });
            }}
          >
            <input
              type="number" min={0} inputMode="decimal" value={min} onChange={(e) => setMin(e.target.value)}
              placeholder={`${labels.priceMin} ${Math.floor(facets.price.min)}`}
              className="w-full min-w-0 rounded border border-border-color px-2 py-1.5 text-sm outline-none focus:border-main"
            />
            <span className="text-gray">-</span>
            <input
              type="number" min={0} inputMode="decimal" value={max} onChange={(e) => setMax(e.target.value)}
              placeholder={`${labels.priceMax} ${Math.ceil(facets.price.max)}`}
              className="w-full min-w-0 rounded border border-border-color px-2 py-1.5 text-sm outline-none focus:border-main"
            />
            <button type="submit" className="shrink-0 rounded bg-primary px-3 py-1.5 text-xs font-bold text-white">
              {labels.applyPrice}
            </button>
          </form>
        </Group>
      )}

      {!!facets.discounted_count && (
        <Group title={labels.discounts}>
          <Check
            checked={filters.discount}
            onChange={() => nav.update({ discount: !filters.discount })}
            label={labels.discountedOnly}
            count={facets.discounted_count}
          />
        </Group>
      )}
    </div>
  );
}
