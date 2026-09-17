import { ApiRequestError, fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  GiftCardBatch,
  GiftCardPurchasesResponse,
  GiftCardsPageContent,
  PaymentOptionsResponse,
  PurchaseGiftCardPayload,
} from "../helpers/types";

/** Shared/public: GET /gift-card-store/available — the browsable catalog of purchasable gift cards. */
export async function getAvailableGiftCards(
  currencyCode: string,
): Promise<GiftCardBatch[]> {
  const envelope = await fetchInstance<ApiEnvelope<GiftCardBatch[]>>(
    `/gift-card-store/available?currency_code=${currencyCode}`,
  );
  return envelope.data;
}

/**
 * Shared/public: GET /gift-card-store/{batch} — a single purchasable gift
 * card batch, backing the storefront detail/purchase page
 * (`/gift-cards/{id}`). Returns `undefined` when the batch doesn't exist,
 * isn't purchasable, or isn't sold in the given currency, so the caller can
 * render a 404.
 */
export async function getGiftCardBatch(
  id: string,
  currencyCode: string,
): Promise<GiftCardBatch | undefined> {
  try {
    const envelope = await fetchInstance<ApiEnvelope<GiftCardBatch>>(
      `/gift-card-store/${id}?currency_code=${currencyCode}`,
    );
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Feature-only: GET /gift-card-store/my-purchases — the customer's gift card purchase history. */
export async function getMyGiftCardPurchases(
  page = 1,
): Promise<GiftCardPurchasesResponse> {
  const envelope = await fetchInstance<ApiEnvelope<GiftCardPurchasesResponse>>(
    `/gift-card-store/my-purchases?page=${page}`,
  );
  return envelope.data;
}

/**
 * Feature-only: GET /checkout/payment-options — active payment gateways for the
 * customer's country. Shared with regular checkout; reused as-is here since it has
 * no cart/checkout-session dependency (just country + optional order_total).
 */
export async function getPaymentOptions(
  orderTotal?: number,
): Promise<PaymentOptionsResponse> {
  const query = orderTotal ? `?order_total=${orderTotal}` : "";
  const envelope = await fetchInstance<ApiEnvelope<PaymentOptionsResponse>>(
    `/checkout/payment-options${query}`,
  );
  return envelope.data;
}

/**
 * Shared/public: GET /page-content/gift-cards — admin-managed banners
 * (`gift_cards_hero` / `gift_cards_redeem` placements) and FAQs (context
 * `gift_cards`) backing the gift cards landing page. Never throws on
 * transport/API failure — the landing page falls back to its hardcoded
 * default banners and hides the FAQ section rather than breaking the page.
 */
export async function getGiftCardsPageContent(): Promise<GiftCardsPageContent> {
  const empty: GiftCardsPageContent = {
    banners: { gift_cards_hero: null, gift_cards_redeem: null },
    faqs: [],
  };

  try {
    const envelope = await fetchInstance<ApiEnvelope<GiftCardsPageContent>>(
      "/page-content/gift-cards",
    );
    return envelope.data ?? empty;
  } catch {
    return empty;
  }
}

/** Feature-only: POST /gift-card-store/purchase — purchases a gift card batch for a recipient. */
export async function purchaseGiftCard(
  payload: PurchaseGiftCardPayload,
): Promise<{ order_id: string; order_number: string; purchases: unknown[] }> {
  const envelope = await fetchInstance<
    ApiEnvelope<{ order_id: string; order_number: string; purchases: unknown[] }>
  >("/gift-card-store/purchase", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return envelope.data;
}

/**
 * Feature-only: POST /orders/{order_number}/bank-transfer-proof — reused as-is
 * (Task 01/02 generalized it to any offline gateway, keyed by order_number, not
 * order type) to submit the gift-card purchase's payment proof + optional note.
 */
export async function uploadGiftCardPaymentProof(
  orderNumber: string,
  file: File,
  note?: string | null,
): Promise<{ proof_uploaded_at: string }> {
  const formData = new FormData();
  formData.append("file", file);
  if (note) {
    formData.append("note", note);
  }
  const envelope = await fetchInstance<
    ApiEnvelope<{ proof_uploaded_at: string }>
  >(`/orders/${orderNumber}/bank-transfer-proof`, {
    method: "POST",
    body: formData,
  });
  return envelope.data;
}
