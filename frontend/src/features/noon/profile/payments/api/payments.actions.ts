import { fetchInstance } from "@/src/lib/utils";
import type {
  AddPaymentMethodPayload,
  ApiEnvelope,
  PaymentMethod,
} from "../helpers/types";

/** Feature-only: GET /payment-methods — the customer's saved payment methods. */
export async function getPaymentMethods(): Promise<PaymentMethod[]> {
  const envelope =
    await fetchInstance<ApiEnvelope<PaymentMethod[]>>("/payment-methods");
  return envelope.data;
}

/** Feature-only: POST /payment-methods — saves a new tokenized payment method. */
export async function addPaymentMethod(
  payload: AddPaymentMethodPayload,
): Promise<PaymentMethod> {
  const envelope = await fetchInstance<ApiEnvelope<PaymentMethod>>(
    "/payment-methods",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}

/** Feature-only: PATCH /payment-methods/:id/default — marks a payment method as default. */
export async function setDefaultPaymentMethod(
  id: string,
): Promise<PaymentMethod> {
  const envelope = await fetchInstance<ApiEnvelope<PaymentMethod>>(
    `/payment-methods/${id}/default`,
    { method: "PATCH" },
  );
  return envelope.data;
}

/** Feature-only: DELETE /payment-methods/:id — removes a saved payment method. */
export async function deletePaymentMethod(id: string): Promise<void> {
  await fetchInstance<unknown>(`/payment-methods/${id}`, {
    method: "DELETE",
  });
}
