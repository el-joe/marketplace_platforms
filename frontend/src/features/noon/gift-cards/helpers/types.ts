export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type GiftCardStatus = "active" | "redeemed" | "expired" | "cancelled";

export type MyGiftCard = {
  id: string;
  code: string;
  denomination: number;
  balance: number;
  currency: string;
  status: GiftCardStatus;
  recipient_email: string | null;
  expires_at: string;
};

export type PurchaseGiftCardPayload = {
  gift_card_batch_id: string;
  quantity?: number;
  country_payment_gateway_id: string;
  recipient_email?: string;
  recipient_name?: string;
  gift_message?: string;
};

/** GET /gift-card-store/available — a purchasable gift card offer (public catalog). */
export type GiftCardBatch = {
  id: string;
  title_en: string;
  title_ar: string;
  description: string;
  amount: string;
  currency_code: string;
  image_url: string;
  min_quantity: number;
  max_quantity: number;
  available_count: number;
};

export type GiftCardDeliveryStatus = "pending" | "delivered" | "failed";

/** The batch a past purchase was bought from, embedded on `GiftCardPurchase`. */
export type GiftCardPurchaseBatch = {
  id: string;
  title_en: string;
  title_ar: string;
  amount: number;
};

/** GET /gift-card-store/my-purchases — a single past gift card purchase/order line. */
export type GiftCardPurchase = {
  id: string;
  order_id: string;
  amount_paid: number;
  currency_code: string;
  is_gift: boolean;
  recipient_email: string | null;
  recipient_name: string | null;
  gift_message: string | null;
  delivery_status: GiftCardDeliveryStatus;
  delivered_at: string | null;
  gift_card_code: string | null;
  batch: GiftCardPurchaseBatch;
  created_at: string;
};

export type GiftCardPurchasesResponse = {
  items: GiftCardPurchase[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};
