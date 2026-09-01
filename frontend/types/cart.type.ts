import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { Warranty } from "@/src/features/noon/productView/types/product-details";

export interface ICart {
  cart: Cart;
  shipping_groups: ShippingGroup[];
  cart_banner: CartBanner;
  savings_and_benefits: SavingsAndBenefits;
  wallet: Wallet;
  guest_cart_token?: string;
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
  item_count: number;
}

export interface CartBanner {
  id: string;
  title_en: string;
  title_ar: string;
  subtitle_en: string;
  subtitle_ar: string;
  cta_label_en: string;
  cta_label_ar: string;
  cta_url: string;
  link_type: string;
  link_reference_id: string | null;
  desktop_image_url: string;
  mobile_image_url: string;
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

export interface ShippingGroup {
  shipping_method: ShippingMethod | null;
  is_free_shipping: boolean;
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
  delivery_label_en: string;
  delivery_label_ar: null | string;
  is_express_type: boolean;
}

export interface ShippingGroupItem {
  id: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  product_url: string;
  product_name_en: string;
  product_name_ar: string;
  variant_name: string;
  primary_image: string | null;
  listing_id: string;
  listing_type: string;
  max_order_quantity: number | null;
  vendor: Vendor;
  selected_shipping_method: SelectedShippingMethod;
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
