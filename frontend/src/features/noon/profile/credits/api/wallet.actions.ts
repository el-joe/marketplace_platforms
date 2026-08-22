import { fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  RequestWithdrawalPayload,
  Wallet,
  WalletTransactionsResponse,
  WithdrawalRequest,
} from "../helpers/types";

/** Feature-only: GET /wallet — the customer's wallet balance. */
export async function getWallet(): Promise<Wallet> {
  const envelope = await fetchInstance<ApiEnvelope<Wallet>>("/wallet");
  return envelope.data;
}

/** Feature-only: GET /wallet/transactions — paginated wallet transaction history. */
export async function getWalletTransactions(
  page = 1,
): Promise<WalletTransactionsResponse> {
  const envelope = await fetchInstance<ApiEnvelope<WalletTransactionsResponse>>(
    `/wallet/transactions?page=${page}`,
  );
  return envelope.data;
}

/** Feature-only: POST /wallet/withdrawal — requests a bank withdrawal from the wallet balance. */
export async function requestWithdrawal(
  payload: RequestWithdrawalPayload,
): Promise<WithdrawalRequest> {
  const envelope = await fetchInstance<ApiEnvelope<WithdrawalRequest>>(
    "/wallet/withdrawal",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}
