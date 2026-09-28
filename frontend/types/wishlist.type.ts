import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { PromoBadge } from "./globals";

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
  type: "product" | "classified";
  listing_type: string | null;
  listing: Partial<ProductListing> & Partial<ClassifiedItem>;
}
export interface ProductItem {
  id: string;
  added_at: Date;
  type: "product";
  listing_type: string | null;
  listing: ProductListing;
}

export interface ProductListing {
  listing_id: string;
  listing_type: string;
  variant_id: string;
  variant_name: string;
  product_url: string;
  url_param: string;
  primary_image: string;
  image: PurpleImage;
  images: ImageElement[];
  price: number;
  compare_at_price: null;
  currency: CurrencyCode;
  condition: string;
  global_system_type: string;
  status: string;
  rating_avg: number;
  rating_count: number;
  promo_badges: PromoBadge[];
  total_sold: number;
  vendor_covers_delivery: boolean;
  international_shipping: null;
  shipping_badge: ShippingBadge;
  brand: Brand;
  product: Product;
  variant: Variant;
}

export interface ClassifiedItem {
  id: string;
  added_at: Date;
  type: "classified";
  listing_type: string | null;
  listing: ClassifiedListing;
}

export interface ClassifiedListing {
  listing_number: string;
  title: Locale;
  price: number;
  price_negotiable: boolean;
  primary_image: string;
  city: Locale;
  seller_label: string;
  listing_purpose: string;
  views_count: number;
  created_at: Date;
}

export interface Brand {
  id: string;
  name: Locale;
  slug: string;
  logo_url: string;
}

export interface Locale {
  ar: null | string;
  en: null | string;
}

export interface PurpleImage {
  url: string;
  alt: Locale;
}

export interface ImageElement {
  id: string;
  url: string;
  alt: Locale;
  is_primary: boolean;
  position: number;
  variant_id?: null | string;
}

export interface Product {
  id: string;
  slug: string;
  name_ar: string;
  name_en: string;
  category: Variant;
  images: ImageElement[];
}

export interface Variant {
  id: string;
  name_ar: null | string;
  name_en: null | string;
  sku?: string;
}

export interface ShippingBadge {
  label: Locale;
  color_hex: string;
  text_color_hex: string;
  icon_color_hex: null | string;
  show_delivery_time: boolean;
  delivery_text: Locale;
  icon: string;
  badge_image_url: null | string;
  delivery_days_min: number;
  delivery_days_max: number;
  is_express: boolean;
}

export type Type = "classified" | "product";
