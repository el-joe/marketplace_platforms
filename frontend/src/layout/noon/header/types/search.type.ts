import { IProduct } from "@/types";
import { ImageDTO } from "@/src/types/media";

export interface ISearchSuggestionProduct {
  id: number | string;
  product_id: number | string;
  slug: string;
  name: string;
  vendor: string;
  type: string;
  image?: ImageDTO | null;
  images?: ImageDTO[];
  // TODO: remove legacy alias fallback once backend drops primary_image/thumbnail/variant_image aliases (see enhancement.md P-17)
  primary_image: string | null;
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
