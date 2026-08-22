// response
// export interface IPrepareCheckout {
//   order_summary: OrderSummary;
//   shipping: Shipping;
//   address: Address;
//   gateway_code: string;
//   gateway_type: string;
//   available_payment_gateways: AvailablePaymentGateway[];
//   coupon: null | Coupon;
//   wallet_balance: number;
//   wallet_currency: string;
//   wallet_applicable: boolean;
//   wallet: Wallet;
//   loyalty: Loyalty;
//   items: Item[];
// }

// export interface Coupon {
//   code: string;
//   type: string;
//   description: string;
// }

// export interface Address {
//   id: number;
//   recipient_name: string;
//   street_address: string;
//   city: string;
//   country: string;
// }

// export interface AvailablePaymentGateway {
//   id: string;
//   gateway_code: string;
//   display_name: DisplayName;
//   type: string;
//   is_redirect: boolean;
//   fee_pct: number;
//   fee_fixed: number;
//   is_configured: boolean;
//   environment: string;
// }

// export interface DisplayName {
//   en: string;
//   ar: string;
// }

// export interface Item {
//   listing_id: string;
//   listing_ref: string;
//   sku: string;
//   name_en: string;
//   quantity: number;
//   unit_price: number;
//   line_total: number;
//   thumbnail: string;
//   vendor_name: string;
//   is_admin_listing: boolean;
// }

// export interface Loyalty {
//   enabled: boolean;
//   balance: number;
//   min_redeem: number;
//   redeem_rate: number;
//   max_discount: number;
//   currency: string;
//   applicable: boolean;
// }

// export interface OrderSummary {
//   subtotal: number;
//   discount: number;
//   shipping: number;
//   cod_fee: number;
//   tax: number;
//   warranty_total: number;
//   gift_card_applied: number;
//   total: number;
//   currency: string;
// }

// export interface Shipping {
//   total_fee: number;
//   is_free: boolean;
//   groups: Group[];
//   delivery_fee: number;
//   is_free_delivery: boolean;
//   vendor_delivery: VendorDelivery[];
// }

// export interface Group {
//   shipping_method_id: string;
//   method_name: string;
//   fee: number;
//   is_free: boolean;
//   estimated_delivery_days_min: number;
//   estimated_delivery_days_max: number;
// }

// export interface VendorDelivery {
//   vendor_id: string;
//   delivery_fee: number;
//   surcharge_applied: boolean;
//   platform_subsidy: number;
//   delivery_message: string;
// }

// export interface Wallet {
//   balance: number;
//   currency_code: string;
//   applicable: boolean;
// }

export interface IPrepareCheckout {
  total_items_qty: number;
  order_summary: OrderSummary;
  shipping: Shipping;
  address: Address;
  receiver: null;
  receivers: unknown[];
  gateway_code: string;
  gateway_type: string;
  available_payment_gateways: AvailablePaymentGateway[];
  coupon: null | Coupon;
  wallet_balance: number;
  wallet_currency: string;
  wallet_applicable: boolean;
  wallet: Wallet;
  loyalty: Loyalty;
  delivery_instructions: DeliveryInstruction[];
  shipment_groups: ShipmentGroup[];
}

export interface Coupon {
  code: string;
  type: string;
  description: string;
}

export interface Address {
  id: number;
  recipient_name: string;
  street_address: string;
  city: string;
  country: string;
}

export interface AvailablePaymentGateway {
  id: string;
  gateway_code: string;
  display_name: DisplayName;
  type: string;
  is_redirect: boolean;
  fee_pct: number;
  fee_fixed: number;
  is_configured: boolean;
  environment: string;
  image_url: string;
}

export interface DisplayName {
  en: string;
  ar: string;
}

export interface DeliveryInstruction {
  value: string;
  label: string;
}

export interface Loyalty {
  enabled: boolean;
  balance: number;
  min_redeem: number;
  redeem_rate: number;
  max_discount: number;
  currency: string;
  applicable: boolean;
}

export interface OrderSummary {
  subtotal: number;
  discount: number;
  shipping: number;
  cod_fee: number;
  tax: number;
  warranty_total: number;
  gift_card_applied: number;
  total: number;
  currency: string;
}

export interface ShipmentGroup {
  shipping_method: ShippingMethod;
  is_free_shipping: boolean;
  group_subtotal: number;
  items_count: number;
  items: Item[];
}

export interface Item {
  id: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  product_url: string;
  product_name_en: string;
  product_name_ar: string;
  max_order_quantity: null | number;
  variant_name: string;
  primary_image: null | string;
  listing_id: string;
  listing_type: string;
  vendor: Vendor;
  selected_shipping_method: SelectedShippingMethod;
}

export interface SelectedShippingMethod {
  id: string;
  name: string;
  code: string;
}

export interface Vendor {
  id: string;
  store_name: string;
}

export interface ShippingMethod {
  id: string;
  name: string;
  code: string;
  badge_label_en: string;
  badge_label_ar: string;
  badge_color_hex: string;
  delivery_label_en: null | string;
  delivery_label_ar: null | string;
  is_express_type: boolean;
}

export interface Shipping {
  total_fee: number;
  is_free: boolean;
  groups: Group[];
  delivery_fee: number;
  is_free_delivery: boolean;
  vendor_delivery: VendorDelivery[];
}

export interface Group {
  shipping_method_id: string;
  method_name: string;
  fee: number;
  is_free: boolean;
  estimated_delivery_days_min: number;
  estimated_delivery_days_max: number;
}

export interface VendorDelivery {
  vendor_id: string;
  delivery_fee: number;
  surcharge_applied: boolean;
  platform_subsidy: number;
  delivery_message: string;
}

export interface Wallet {
  balance: number;
  currency_code: string;
  applicable: boolean;
}
