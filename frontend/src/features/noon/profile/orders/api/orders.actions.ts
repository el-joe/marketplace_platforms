import { fetchInstance, ApiRequestError } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  ListOrdersFilters,
  OrderDetail,
  OrdersListResponse,
  RequestReturnPayload,
  ReturnRequestResult,
} from "../helpers/types";

/** Feature-only: GET /orders — paginated order history for the authenticated customer. */
export async function getOrders(
  filters: ListOrdersFilters = {},
): Promise<OrdersListResponse> {
  const params = new URLSearchParams();
  if (filters.status) params.set("status", filters.status);
  if (filters.date_from) params.set("date_from", filters.date_from);
  if (filters.date_to) params.set("date_to", filters.date_to);
  if (filters.page) params.set("page", String(filters.page));

  const query = params.toString();
  const envelope = await fetchInstance<ApiEnvelope<OrdersListResponse>>(
    `/orders${query ? `?${query}` : ""}`,
  );
  return envelope.data;
}

/** Feature-only: GET /orders/:order_number — rich order-tracking detail. Returns undefined on 404. */
export async function getOrderByNumber(
  orderNumber: string,
): Promise<OrderDetail | undefined> {
  try {
    const envelope = await fetchInstance<ApiEnvelope<OrderDetail>>(
      `/orders/${orderNumber}`,
    );
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Feature-only: submits a return request for selected order items. POST /orders/:order_number/returns */
export async function requestReturn(
  orderNumber: string,
  payload: RequestReturnPayload,
): Promise<ReturnRequestResult> {
  const envelope = await fetchInstance<ApiEnvelope<ReturnRequestResult>>(
    `/orders/${orderNumber}/returns`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  return envelope.data;
}
