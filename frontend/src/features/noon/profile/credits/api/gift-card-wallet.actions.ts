import { fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  GiftCardWalletResponse,
  RedeemGiftCardPayload,
  RedeemVoucherPayload,
  RedeemResult,
} from "../helpers/types";

/** Feature-only: GET /gift-card-wallet — the customer's gift card & voucher wallet balances. */
export async function getGiftCardWallet(): Promise<GiftCardWalletResponse> {
  const envelope = await fetchInstance<ApiEnvelope<GiftCardWalletResponse>>(
    "/gift-card-wallet",
  );
  return envelope.data;
}

/** Feature-only: POST /gift-card-wallet/redeem-gift-card — redeems a gift card code + pin into the wallet. */
export async function redeemGiftCard(
  payload: RedeemGiftCardPayload,
): Promise<RedeemResult> {
  const envelope = await fetchInstance<ApiEnvelope<RedeemResult>>(
    "/gift-card-wallet/redeem-gift-card",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}

/** Feature-only: POST /gift-card-wallet/redeem/voucher — redeems a voucher code into the wallet. */
export async function redeemVoucher(
  payload: RedeemVoucherPayload,
): Promise<RedeemResult> {
  const envelope = await fetchInstance<ApiEnvelope<RedeemResult>>(
    "/gift-card-wallet/redeem/voucher",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}
