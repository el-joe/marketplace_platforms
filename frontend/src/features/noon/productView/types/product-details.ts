import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { IProduct } from "@/types";

export interface IProductDetails {
  listing: Listing;
  seller: Seller;
  delivery_options: DeliveryOption[];
  best_seller_badge: null;
  coupons: Coupon[];
  payment_options: PaymentOption[];
  product: Product;
  product_attributes: ProductAttribute[];
  variant: Variant;
  other_sellers: OtherSeller[];
  other_variants: Variant[];
  reviews: Reviews;
  frequently_bought_together: FrequentlyBoughtTogether;
  related_products: IProduct[];
  warranty_plans: Warranty[];
}

export interface DeliveryOption {
  shipping_method_id: string;
  code: string;
  name: Locales;
  badge_label: Locales;
  is_express: boolean;
  is_primary: boolean;
  shipping_fee: number;
  is_free: boolean;
  estimated_delivery_date: Date;
  order_before_seconds: number | null;
}

export interface Coupon {
  id: string;
  code: string;
  name: string;
  type: string;
  estimated_saving: number;
  valid_until: Date;
  learn_more_url: string;
  is_bank_offer: boolean;
  bank_name: string;
  title: Locales;
  terms: Locales;
  max_orders_per_customer_per_month: number;
}

export interface Locales {
  ar: string[] | null | string;
  en: string[] | null | string;
}

export interface FrequentlyBoughtTogether {
  items: Item[];
  total_price: number;
  total_price_formatted: string;
  currency: CurrencyCode;
}

export interface Item {
  product_id: string;
  listing_id: string;
  listing_ref: string;
  name: Locales;
  image_url: string;
  price: number;
  price_formatted: string;
  currency: CurrencyCode;
}

export interface Listing {
  listing_id: string;
  listing_ref: string;
  vendor_sku: string | null;
  sku: string;
  price: number;
  price_formatted: string;
  currency: CurrencyCode;
  condition: string;
  condition_notes: string | null;
  is_admin_listing: boolean;
  is_express_fbn: boolean;
  fulfillment_model: string;
  global_system_type: string;
  status: string;
  max_order_quantity: number;
  total_sold: number;
  rating_avg: number | null;
  rating_count: number;
  is_global_shipping: boolean;
  is_wishlisted: boolean;
}

export interface OtherSeller {
  listing_id: string;
  is_selected: boolean;
  listing_ref: string;
  url: string;
  seller_name: string;
  seller_rating: number;
  price: number;
  price_formatted: string;
  currency: CurrencyCode;
  condition: string;
  is_admin_listing: boolean;
  is_express_fbn: boolean;
  shipping_badge: ShippingBadge | null;
}

export interface ShippingBadge {
  label: Locales;
  color_hex: string;
  text_color_hex: string;
  delivery_days_min: number;
  delivery_days_max: number;
}

export interface PaymentOption {
  method_type: string;
  provider: string;
  display_name: Locales;
  installments_count: number;
  installment_amount: number;
  label: string;
  provider_logo_path: string;
  learn_more_url: string | null;
}

export interface Product {
  id: string;
  slug: string;
  name: Locales;
  description: Locales;
  brand: Brand;
  category: Brand;
  breadcrumbs: Brand[];
  images: Image[];
  rating_avg: number;
  rating_count: number;
  attributes_summary: Locales;
  highlights: string[];
  specifications: Specifications[];
  seo: SEO;
}
export interface Specifications {
  label: string;
  value: string;
}

export interface Brand {
  id: string;
  name: Locales;
  slug: string;
  is_verified?: boolean;
}

export interface Image {
  url: string;
  is_primary: boolean;
}

export interface SEO {
  title: Locales;
  description: Locales;
}

export interface ProductAttribute {
  attribute_id: string;
  name: Locales;
  values: Value[];
}

export interface Value {
  attribute_value_id: string;
  slug: string;
  value: Locales;
  url: null | string;
  url_param: null | string;
  color_hex: string | null;
  selected: boolean;
  disabled: boolean;
  variant_id: null | string;
  listing_id: null | string;
  listing_ref: null | string;
}

export interface Reviews {
  rating_avg: number;
  rating_count: number;
  rating_percentage: number;
  rating_breakdown: RatingBreakdown[];
  items: ReviewsItem[];
}

export interface ReviewsItem {
  id: string;
  rating: number;
  title: null | string;
  body: string;
  is_verified_purchase: boolean;
  helpful_count: number;
  not_helpful_count: number;
  reviewer_name: string;
  created_at: Date;
}

export interface RatingBreakdown {
  stars: number;
  count: number;
  percentage: number;
}

export interface Seller {
  id: string;
  store_name: string;
  rating_avg: number;
  rating_count: number;
  is_admin_listing: boolean;
  vendor_details: VendorDetails;
}
export interface VendorDetails {
  rating_avg: number;
  rating_count: number;
  positive_rating_pct: number;
  item_as_shown_pct: number;
  partner_since_years: null | string;
  warranty_months: null | string;
  easy_returns_enabled: boolean;
  secure_payments_enabled: boolean;
}

export interface Variant {
  id: string;
  sku: string;
  barcode: string | null;
  variant_name: string;
  is_default: boolean;
  attributes: Attribute[];
}

export interface Attribute {
  attribute_name: Locales;
  value: Locales;
}

export interface Warranty {
  id: string;
  name: string;
  duration_months: number;
  duration_label: string;
  features: string[];
  price: number;
  currency: CurrencyCode;
}
