import { fetchInstance } from "@/src/lib/utils";
import type { ApiEnvelope, PaymentHistoryResponse } from "../helpers/types";

/** Feature-only: GET /payment-history — paginated payment transaction history. */
export async function getPaymentHistory(
  page = 1,
): Promise<PaymentHistoryResponse> {
  const envelope = await fetchInstance<ApiEnvelope<PaymentHistoryResponse>>(
    `/payment-history?page=${page}`,
  );
  return envelope.data;
}
