export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type ClassifiedListing = {
  listing_id: string;
  listing_number: string;
  source_type: "classified";
  title_en: string;
  title_ar: string;
  slug: string;
  thumbnail: string | null;
  price: number; // raw BIGINT base-currency — display directly
  price_formatted: string; // pre-divided string e.g. "500.00"
  currency: string;
  price_negotiable: boolean;
  listing_purpose: "sale" | "rent";
  location: string | null;
  seller_type: "vendor" | "customer";
  created_at: string;
};

export type ClassifiedCategory = {
  id: string;
  source_type: "classified";
  name: { ar: string; en: string };
  slug: string;
  icon: string | null;
  link: string;
};

export type ClassifiedMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type ClassifiedBrowseResponse = {
  category: ClassifiedCategory;
  page_builder: unknown;
  listings: {
    items: ClassifiedListing[];
    meta: ClassifiedMeta;
  };
};

export type ClassifiedBrowseFilters = {
  page?: number;
  per_page?: number;
  min_price?: number;
  max_price?: number;
  listing_purpose?: "sale" | "rent";
  seller_type?: "vendor" | "customer";
};
