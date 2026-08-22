import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface IWishlist {
  group: IWishlistGroup;
  items: Item[];
}
export interface IWishlistGroup {
  id: string;
  name: string;
  is_default: boolean;
  is_public: boolean;
  sort_order: number;
  items_count: number;
  created_at: Date;
}

export interface Item {
  id: string;
  added_at: Date;
  listing_type: string;
  listing: Listing;
}

export interface Listing {
  listing_id: string;
  listing_type: string;
  variant_id: string;
  variant_name: string;
  product_url: string;
  url_param: string;
  primary_image: string;
  price: number;
  compare_at_price: null;
  currency: CurrencyCode;
  condition: string;
  global_system_type: string;
  status: string;
  rating_avg: number;
  rating_count: number;
  total_sold: number;
  vendor_covers_delivery: boolean;
  product: Product;
  variant: Variant;
}

export interface Product {
  id: string;
  slug: string;
  name_ar: string;
  name_en: string;
  category: Variant;
  images: Image[];
}

export interface Variant {
  id: string;
  name_ar: null | string;
  name_en: null | string;
  sku?: string;
}

export interface Image {
  url: string;
  is_primary: boolean;
}
