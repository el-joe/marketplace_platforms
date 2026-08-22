import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface IPlaceOrderResponse {
  order_number: string;
  status: string;
  payment_status: string;
  total: number;
  warranty_total: number;
  currency: CurrencyCode;
  placed_at: Date;
  sub_orders: SubOrder[];
  wallet: null;
  payment_redirect_url: null | string;
  requires_redirect: boolean;
  bank_transfer_details: null;
}

export interface SubOrder {
  sub_order_number: string;
  vendor: string;
  status: string;
  fulfillment_model: string;
  delivery_fee: number;
  is_free_delivery: boolean;
  items: Item[];
}

export interface Item {
  listing_ref: string;
  sku: string;
  name_en: string;
  quantity: number;
  unit_price: number;
  line_total: number;
}

// payload

export interface IPlaceOrderPayload {
  address_id: number;
  idempotency_key: string;
  country_payment_gateway_id: string;
}
