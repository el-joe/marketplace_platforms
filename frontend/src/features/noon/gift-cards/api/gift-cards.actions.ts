import { fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  GiftCardBatch,
  GiftCardPurchasesResponse,
  MyGiftCard,
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

/** Feature-only: GET /gift-card-store/my-purchases — the customer's gift card purchase history. */
export async function getMyGiftCardPurchases(
  page = 1,
): Promise<GiftCardPurchasesResponse> {
  const envelope = await fetchInstance<ApiEnvelope<GiftCardPurchasesResponse>>(
    `/gift-card-store/my-purchases?page=${page}`,
  );
  return envelope.data;
}

/** Feature-only: POST /gift-cards/purchase — purchases a new gift card for a recipient. */
export async function purchaseGiftCard(
  payload: PurchaseGiftCardPayload,
): Promise<MyGiftCard> {
  const envelope = await fetchInstance<ApiEnvelope<MyGiftCard>>(
    "/gift-cards/purchase",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}
