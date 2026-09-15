import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface Name {
  ar: null | string;
  en: null | string;
}

export interface MarketerSeller {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate";
  profile_slug: string | null;
  profile_url: string | null;
}

export interface Product {
  listing_id: string;
  listing_type: "admin" | "vendor" | "marketer";
  listing_ref: string;
  sku: string;
  vendor_sku: null | string;
  product_id: string;
  product_slug: string;
  slug: string;
  variant_id: string;
  variant_slug: string;
  product_url: string;
  url_param: string;
  variant_name: Name;
  variant_image: string;
  primary_image: string;
  name_en: string;
  name_ar: string;
  thumbnail: string;
  images: Images[];
  category_name?: { en: string | null; ar: string | null };
  price: number;
  price_formatted: string;
  compare_at_price?: number | null;
  currency: CurrencyCode;
  condition: string;
  is_admin_listing: boolean;
  is_express_fbn: boolean;
  fulfillment_model: string;
  vendor: Vendor;
  marketer?: MarketerSeller | null;
  referral_code?: string | null;
  referral_link?: string | null;
  shipping_badge: ShippingBadge | null;
  rating_avg: null | number;
  rating_count: number;
  total_sold: number;
  is_wishlisted: boolean;
  is_sponsored: boolean;
  has_custom_attributes?: boolean;
  custom_attributes?: {
    id: string;
    label: string;
    unit: string | null;
    is_required: boolean;
    sort_order: number;
  }[];
  campaign_context?: { id: string; title: string | null; vendor_name: string | null } | null;
}

export interface Images {
  id: string;
  url: string;
  alt: Name;
  is_primary: boolean;
  position: number;
  variant_id: null | string;
}

export interface Category {
  id: string;
  name: Name;
  slug: string;
}

export interface Vendor {
  id: string;
  store_name: string;
  rating: number;
}

export interface ShippingBadge {
  label_en?: string;
  label_ar?: string;
  color_hex: string;
  text_color_hex: string;
  delivery_days_min: number | null;
  delivery_days_max: number | null;
  label?: Name;
}

export interface SEO {
  title: null | string;
  description: null | string;
  og_image_url: null | string;
}
