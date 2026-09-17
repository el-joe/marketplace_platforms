import { ApiRequestError, fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  GiftCardBatch,
  GiftCardPurchasesResponse,
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

/** Feature-only: POST /gift-card-store/purchase — purchases a gift card batch for a recipient. */
export async function purchaseGiftCard(
  payload: PurchaseGiftCardPayload,
): Promise<{ order_id: string; purchases: unknown[] }> {
  const envelope = await fetchInstance<
    ApiEnvelope<{ order_id: string; purchases: unknown[] }>
  >("/gift-card-store/purchase", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return envelope.data;
}
