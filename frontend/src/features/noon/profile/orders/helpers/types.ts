export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type OrderStatus =
  | "placed"
  | "confirmed"
  | "partially_shipped"
  | "shipped"
  | "partially_delivered"
  | "delivered"
  | "completed"
  | "cancelled"
  | "refunded"
  | "disputed";

export type PaymentMethod = "card" | "wallet" | "cod" | "bnpl" | "bank_transfer";

// ---- List Orders (GET /orders) ----

export type OrderListItemLine = {
  id: string;
  product: Record<string, unknown>;
  sku: string;
  listing_ref: string;
  vendor_sku: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  fulfillment_status: string;
  return_eligible_until: string | null;
};

export type OrderListSubOrder = {
  id: string;
  sub_order_number: string;
  status: string;
  fulfillment_model: string;
  vendor_name: string;
  subtotal: number;
  shipping: number;
  tax: number;
  tracking_number: string | null;
  estimated_delivery_date: string | null;
  sla_ship_deadline: string | null;
  items: OrderListItemLine[];
};

export type OrderListItem = {
  id: string;
  order_number: string;
  status: OrderStatus;
  payment_status: string;
  payment_method: PaymentMethod;
  currency: string;
  subtotal: number;
  discount: number;
  shipping: number;
  tax: number;
  cod_fee: number;
  total: number;
  placed_at: string;
  shipping_address: {
    recipient_name: string;
    street_address: string;
  };
  coupon_code_used: string | null;
  sub_orders: OrderListSubOrder[];
};

export type OrdersListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type OrdersListResponse = {
  items: OrderListItem[];
  meta: OrdersListMeta;
};

export type ListOrdersFilters = {
  status?: OrderStatus;
  date_from?: string;
  date_to?: string;
  page?: number;
};

// ---- Get Order Detail (GET /orders/:order_number) ----

export type TrackingEvent = {
  status: string;
  status_label_en: string;
  status_label_ar: string;
  location: string | null;
  description: string | null;
  occurred_at: string;
};

export type OrderWarrantySummary = {
  id: string;
  status: "pending" | "active" | "expired" | "cancelled";
  coverage_starts_at: string | null;
  coverage_ends_at: string | null;
  price_paid: number;
  currency: string;
  is_claimable: boolean;
};

export type OrderDetailItem = {
  id: string;
  sku: string;
  listing_ref: string;
  name_en: string;
  name_ar: string;
  thumbnail: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
  fulfillment_status: string;
  return_eligible_until: string | null;
  can_return: boolean;
  can_review: boolean;
  warranty: OrderWarrantySummary | null;
  can_claim_warranty: boolean;
};

export type OrderDetailSubOrder = {
  id: string;
  sub_order_number: string;
  status: string;
  fulfillment_model: string;
  vendor: {
    id: string;
    store_name: string;
  };
  tracking: {
    tracking_number: string | null;
    carrier: string | null;
    estimated_delivery_date: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    events: TrackingEvent[];
  };
  delivery_agent: {
    name: string;
    status: string;
    otp_required: boolean;
    otp_verified: boolean;
  } | null;
  items: OrderDetailItem[];
};

export type OrderDetail = {
  order_number: string;
  status: OrderStatus;
  status_label_en: string;
  status_label_ar: string;
  payment_method: PaymentMethod;
  payment_status: string;
  placed_at: string;
  currency: string;
  summary: {
    subtotal: number;
    discount: number;
    shipping: number;
    cod_fee: number;
    tax: number;
    total: number;
  };
  shipping_address: {
    recipient_name: string;
    recipient_phone: string;
    street_address: string;
    area: string;
    city: string;
    country: string;
  };
  sub_orders: OrderDetailSubOrder[];
  marketer_ref: string | null;
};

// ---- Request Return (POST /orders/:order_number/returns) ----

export type ReturnReason =
  | "changed_mind"
  | "wrong_item"
  | "defective"
  | "damaged"
  | "not_as_described"
  | "size_issue"
  | "quality_issue"
  | "arrived_late"
  | "other";

export type ReturnType = "refund" | "exchange" | "store_credit";

export type RequestReturnPayload = {
  order_item_ids: string[];
  reason: ReturnReason;
  return_type: ReturnType;
  comments?: string;
};

export type ReturnRequestResult = {
  id: string;
  return_number: string;
  order_number: string;
  reason: ReturnReason;
  reason_description: string | null;
  return_type: ReturnType;
  status: string;
  refund_amount: number | null;
  rejection_reason: string | null;
  created_at: string;
};

// ---- List Returns (GET /returns) / Get Return Detail (GET /returns/:return_number) ----

/**
 * The API docs never spell out product_snapshot's fields (every example shows
 * it as `{}`) — named after OrderDetailItem's shape since that's what it's a
 * snapshot of, so these are treated as optional/best-effort until confirmed
 * against a real response.
 */
export type ReturnItemProductSnapshot = {
  name_en?: string;
  name_ar?: string;
  sku?: string;
  thumbnail?: string | null;
  unit_price?: number;
  line_total?: number;
};

export type ReturnListItemLine = {
  order_item_id: string;
  quantity: number;
  product_snapshot: ReturnItemProductSnapshot;
};

export type ReturnListItem = ReturnRequestResult & {
  items: ReturnListItemLine[];
};

export type ReturnDetail = ReturnRequestResult;

export type ReturnsListMeta = OrdersListMeta;

export type ReturnsListResponse = {
  items: ReturnListItem[];
  meta: ReturnsListMeta;
};

export type ListReturnsFilters = {
  page?: number;
};
