import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface IPlaceOrderResponse {
  order_number: string;
  status: string;
  payment_status: string;
  total: number;
  warranty_total: number;
  currency: CurrencyCode;
  placed_at: string;
  sub_orders: SubOrder[];
  wallet: WalletSummary | null;
  payment_redirect_url: null | string;
  requires_redirect: boolean;
  bank_transfer_details: Record<string, unknown> | null;
}

export interface WalletSummary {
  wallet_amount_used: number;
  remaining_paid: number;
  fully_paid_by_wallet: boolean;
  currency_code: string;
  new_wallet_balance: number;
}

export interface SubOrder {
  sub_order_number: string;
  vendor: string | null;
  status: string;
  fulfillment_model: string;
  delivery_fee: number;
  is_free_delivery: boolean;
  items: Item[];
}

export interface Item {
  listing_ref: string | null;
  sku: string;
  name_en: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
}

// payload

export interface IPlaceOrderPayload {
  address_id: number;
  idempotency_key: string;
  country_payment_gateway_id: string;
  receiver_id?: string | null;
  delivery_instruction?: string | null;
  coupon_code?: string | null;
  customer_notes?: string | null;
  wallet_amount_to_use?: number | null;
  warranty_selections?:
    | {
        listing_id: string;
        warranty_plan_id: string;
      }[]
    | null;
  loyalty_points_to_use?: number | null;
}
