import type { ListingKind, ListingSort, ListingSource, MarketerFilters } from "./types";

export const LISTINGS_PER_PAGE = 24;

export const SOURCE_OPTIONS: ListingSource[] = ["all", "own", "for_sellers"];
export const KIND_OPTIONS: ListingKind[] = ["all", "products", "classified"];
export const SORT_OPTIONS: ListingSort[] = ["newest", "price_asc", "price_desc"];

export const DEFAULT_FILTERS: MarketerFilters = {
  source: "all",
  kind: "all",
  q: "",
  category: [],
  brand: [],
  priceMin: "",
  priceMax: "",
  discount: false,
  sort: "newest",
};

/** Facet lists collapse to this many rows until "show more" is pressed. */
export const FACET_COLLAPSED_ROWS = 8;
export const SEARCH_DEBOUNCE_MS = 400;
