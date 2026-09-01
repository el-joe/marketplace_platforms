import { fetchInstance } from "@/src/lib/utils";
import { ICartResponseBody } from "@/src/services/cart";

export const updateCartItemWarranty = (
  cartItemId: string,
  warrantyPlanId: string | null,
) =>
  fetchInstance<ICartResponseBody>(`/cart/items/${cartItemId}/warranty`, {
    method: "PATCH",
    body: JSON.stringify({ warranty_plan_id: warrantyPlanId }),
  });
