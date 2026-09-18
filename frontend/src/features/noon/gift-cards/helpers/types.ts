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

/** A single entry from GET /checkout/payment-options, as consumed by the gift-card selector. */
export type PaymentOption = {
  id: string;
  gateway_code: string | null;
  type: string | null;
  display_name: { en: string; ar: string };
  image: string | null;
  is_redirect: boolean;
  fee_pct: number;
  fee_fixed: number;
  gateway_fee: number;
  is_available: boolean;
  unavailable_reason: string | null;
  environment: string;
};

export type PaymentOptionsResponse = {
  payment_options: PaymentOption[];
  wallet: {
    balance: number;
    currency_code: string;
    applicable: boolean;
  };
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

/**
 * GET /page-content/gift-cards — an admin-managed banner for a given
 * placement (`gift_cards_hero` | `gift_cards_redeem`), mirroring
 * `App\Http\Resources\Customer\BannerResource` on the backend. Any field
 * can be null when the admin hasn't filled it in for that slot.
 */
export type PageContentBanner = {
  id: string;
  title_en: string | null;
  title_ar: string | null;
  subtitle_en: string | null;
  subtitle_ar: string | null;
  cta_label_en: string | null;
  cta_label_ar: string | null;
  cta_url: string | null;
  link_type: string | null;
  link_reference_id: string | null;
  desktop_image_url: string | null;
  mobile_image_url: string | null;
  product_id: string | null;
};

/** GET /page-content/gift-cards — a single admin-managed FAQ entry (context = 'gift_cards'). */
export type PageContentFaq = {
  id: string;
  question_en: string;
  question_ar: string;
  answer_en: string;
  answer_ar: string;
  sort_order: number;
};

/** GET /page-content/gift-cards — content backing the gift cards landing page. */
export type GiftCardsPageContent = {
  banners: {
    gift_cards_hero: PageContentBanner | null;
    gift_cards_redeem: PageContentBanner | null;
  };
  faqs: PageContentFaq[];
};
