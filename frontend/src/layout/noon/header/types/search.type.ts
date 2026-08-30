import { IProduct } from "@/types";

export interface ISearchSuggestionProduct {
  id: number | string;
  product_id: number | string;
  slug: string;
  name: string;
  vendor: string;
  type: string;
}

export interface ISearchSuggestionCategory {
  id: string;
  source_type: string;
  name: {
    ar: string;
    en: string;
  };
  slug: string;
  icon: null | string;
  link: string;
}

export interface ISearchSuggestionVendor {
  id: number | string;
  store_name: string;
  slug: string;
  rating?: number;
}

export interface ISearchSuggestionsData {
  queries: string[];
  products: ISearchSuggestionProduct[];
  categories: ISearchSuggestionCategory[];
  vendors: ISearchSuggestionVendor[];
  trending: string[];
}

export interface ISearchSuggestionsResponse {
  success?: boolean;
  message?: string;
  data: ISearchSuggestionsData;
}

export interface ISearchResponse {
  success?: boolean;
  message?: string;
  data: {
    items: IProduct[];
    facets?: Record<string, unknown>;
    meta: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}
