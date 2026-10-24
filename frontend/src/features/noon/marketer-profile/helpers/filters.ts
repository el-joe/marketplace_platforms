import {
  DEFAULT_FILTERS,
  KIND_OPTIONS,
  SORT_OPTIONS,
  SOURCE_OPTIONS,
} from "./constants";
import type { ListingKind, ListingSort, ListingSource, MarketerFilters } from "./types";

type RawParams = Record<string, string | string[] | undefined>;

const first = (v: string | string[] | undefined): string =>
  Array.isArray(v) ? (v[0] ?? "") : (v ?? "");

const many = (v: string | string[] | undefined): string[] =>
  (Array.isArray(v) ? v : v ? [v] : []).filter(Boolean);

const num = (v: string): string => (/^\d+(\.\d+)?$/.test(v) ? v : "");

/** URL search params (RSC `searchParams`) -> validated filter object. */
export function parseFilters(raw: RawParams): MarketerFilters {
  const source = first(raw.source) as ListingSource;
  const kind = first(raw.kind) as ListingKind;
  const sort = first(raw.sort) as ListingSort;
  return {
    source: SOURCE_OPTIONS.includes(source) ? source : DEFAULT_FILTERS.source,
    kind: KIND_OPTIONS.includes(kind) ? kind : DEFAULT_FILTERS.kind,
    q: first(raw.q).trim().slice(0, 100),
    category: many(raw.category),
    brand: many(raw.brand),
    priceMin: num(first(raw.price_min)),
    priceMax: num(first(raw.price_max)),
    discount: first(raw.discount) === "1",
    sort: SORT_OPTIONS.includes(sort) ? sort : DEFAULT_FILTERS.sort,
  };
}

/** Filters -> browser URL query (only non-default values). */
export function filtersToUrlParams(f: MarketerFilters): URLSearchParams {
  const p = new URLSearchParams();
  if (f.source !== "all") p.set("source", f.source);
  if (f.kind !== "all") p.set("kind", f.kind);
  if (f.q) p.set("q", f.q);
  f.category.forEach((c) => p.append("category", c));
  f.brand.forEach((b) => p.append("brand", b));
  if (f.priceMin) p.set("price_min", f.priceMin);
  if (f.priceMax) p.set("price_max", f.priceMax);
  if (f.discount) p.set("discount", "1");
  if (f.sort !== DEFAULT_FILTERS.sort) p.set("sort", f.sort);
  return p;
}

/** Filters -> backend query (`category[]`, `brand[]` per the API contract). */
export function filtersToApiParams(f: MarketerFilters, page: number, perPage: number): URLSearchParams {
  const p = new URLSearchParams();
  p.set("source", f.source);
  p.set("kind", f.kind);
  if (f.q) p.set("q", f.q);
  f.category.forEach((c) => p.append("category[]", c));
  f.brand.forEach((b) => p.append("brand[]", b));
  if (f.priceMin) p.set("price_min", f.priceMin);
  if (f.priceMax) p.set("price_max", f.priceMax);
  if (f.discount) p.set("discount", "1");
  p.set("sort", f.sort);
  p.set("page", String(page));
  p.set("per_page", String(perPage));
  return p;
}

export function countActiveFilters(f: MarketerFilters): number {
  return (
    (f.source !== "all" ? 1 : 0) +
    (f.kind !== "all" ? 1 : 0) +
    (f.q ? 1 : 0) +
    f.category.length +
    f.brand.length +
    (f.priceMin || f.priceMax ? 1 : 0) +
    (f.discount ? 1 : 0)
  );
}
