import { CustomAttributeValue } from "@/src/components/shared/custom-attribute-values";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { Warranty } from "@/src/features/noon/productView/types/product-details";
import { PlacementBanner } from "@/src/types/placement-banner";
import { IProduct } from "@/types/product.type";
import { PromoBadge } from "./globals";
import { ImageDTO } from "@/src/types/media";

export interface ICart {
  cart: Cart;
  shipping_groups: ShippingGroup[];
  cart_banner: PlacementBanner | null;
  savings_and_benefits: SavingsAndBenefits;
  wallet: Wallet;
  guest_cart_token?: string;
  suggested_products?: IProduct[];
}

export interface Cart {
  cart_id: string;
  session_token?: string;
  currency: CurrencyCode;
  summary: Summary;
  coupon: Coupon | null;
  items: CartItem[];
  expires_at: Date;
}

export interface Coupon {
  code: string;
  type: string;
  description: string;
}

export interface CartItem {
  cart_item_id: string;
  listing_id: string;
  listing_ref: string;
  sku: string;
  vendor_sku: null;
  name: Name;
  thumbnail: string;
  unit_price: number;
  quantity: number;
  line_total: number;
  max_order_quantity: number;
  vendor: Vendor;
  is_admin_listing: boolean;
  shipping_badge: null;
  in_stock: boolean;
  price_changed: boolean;
  selected_shipping_method: null;
  applicable_coupons: ApplicableCoupon[];
  warranty_plan_id: string | null;
  available_warranty_plans: Warranty[];
}

export interface ApplicableCoupon {
  id: string;
  code: string;
  name: string;
  type: string;
  estimated_saving: number;
  valid_until: Date;
  learn_more_url: string;
  is_bank_offer: boolean;
  bank_name: null | string;
  title: Name;
  terms: Terms;
  max_orders_per_customer_per_month: number | null;
}

export interface Terms {
  ar: string[];
  en: string[];
}

export interface Name {
  ar: string;
  en: string;
}

export interface Vendor {
  id: string;
  store_name: string;
}

export interface Summary {
  subtotal: number;
  discount: number;
  estimated_shipping: number;
  estimated_tax: number;
  estimated_total: number;
  /** Selected extended warranties (one plan price per line), included in estimated_total. */
  warranty_total?: number;
  item_count: number;
}

export interface SavingsAndBenefits {
  installments: Installment[];
  card_offers: CardOffer[];
}

export interface CardOffer {
  type: string;
  card_name_en: string;
  card_name_ar: string | null;
  bank_name_en: string | null;
  bank_name_ar: string | null;
  card_image_url: string | null;
  cashback_type: string;
  cashback_pct: string;
  cashback_amount: number;
  label_en: string;
  label_ar: string | null;
  apply_url: string | null;
  apply_label_en: string;
  apply_label_ar: string;
  sort_order: number;
}

export interface Installment {
  type: string;
  provider: string;
  display_name_en: string;
  display_name_ar: string;
  logo_url: null | string;
  installments_count: number;
  installment_amount: number | null;
  currency: null | CurrencyCode;
  label_en: string;
  label_ar: string;
  learn_more_url: string | null;
  sort_order: number;
  remainder?: number;
}

/** Distance-based delivery outcome for a shipping group (null when the zone/method has no distance rule). */
export interface DistancePricing {
  status:
    | "unavailable"
    | "free_by_order_amount"
    | "free_within_radius"
    | "tier"
    | "fallback";
  available: boolean;
  distance_km: number | null;
  fee: number;
  free_radius_km: number | null;
  max_service_radius_km: number | null;
  tier: { from_km: number; to_km: number | null; price: number } | null;
  fallback_reason: string | null;
  message: string;
}

export interface ShippingGroup {
  shipping_method: ShippingMethod | null;
  is_free_shipping: boolean;
  distance_pricing?: DistancePricing | null;
  group_subtotal: number;
  items_count: number;
  items: ShippingGroupItem[];
}

export interface ShippingMethod {
  id: string;
  name: string;
  code: string;
  badge_label_en: string;
  badge_label_ar: string;
  badge_color_hex: string;
  is_express_type: boolean;
  /** Latest promised delivery day (YYYY-MM-DD, country timezone) — "Get it by …". */
  estimated_delivery_date?: string | null;
  /** End of today's order window (ISO-8601); null when none or already passed. */
  order_cutoff_at?: string | null;
}

export interface WarrantyPlanSummary {
  id: string;
  name: string;
  duration_months: number;
  duration_label: string;
  price: number;
  currency: string;
  image_url: string | null;
}

export interface ShippingGroupItem {
  promo_badges?: PromoBadge[];
  id: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  product_url: string;
  product_name_en: string;
  product_name_ar: string;
  variant_name: string;
  image?: ImageDTO | null;
  images?: ImageDTO[];
  // TODO: remove legacy alias fallback once backend drops primary_image/thumbnail/variant_image aliases (see enhancement.md P-17)
  primary_image: string | null;
  listing_id: string;
  listing_type: string;
  max_order_quantity: number | null;
  /** Null for platform (admin) and marketer listings. */
  vendor: Vendor | null;
  selected_shipping_method: SelectedShippingMethod;
  warranty_plan: WarrantyPlanSummary | null;
  /** Values the customer entered for made-to-measure products. */
  custom_attributes?: CustomAttributeValue[];
}

export interface SelectedShippingMethod {
  id: string | null;
  name: string | null;
  code: string | null;
}

export interface Wallet {
  balance: number;
  currency_code: CurrencyCode;
  applicable: boolean;
  max_usable: number;
  remaining_after_wallet: number;
}
