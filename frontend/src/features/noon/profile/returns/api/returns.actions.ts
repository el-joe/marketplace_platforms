import { fetchInstance, ApiRequestError } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  ListReturnsFilters,
  ReturnDetail,
  ReturnsListResponse,
} from "../../orders/helpers/types";

/** Feature-only: GET /returns — paginated return-request history for the authenticated customer. */
export async function getReturns(
  filters: ListReturnsFilters = {},
): Promise<ReturnsListResponse> {
  const params = new URLSearchParams();
  if (filters.page) params.set("page", String(filters.page));

  const query = params.toString();
  const envelope = await fetchInstance<ApiEnvelope<ReturnsListResponse>>(
    `/returns${query ? `?${query}` : ""}`,
  );
  return envelope.data;
}

/** Feature-only: GET /returns/:return_number — return request detail. Returns undefined on 404. */
export async function getReturnByNumber(
  returnNumber: string,
): Promise<ReturnDetail | undefined> {
  try {
    const envelope = await fetchInstance<ApiEnvelope<ReturnDetail>>(
      `/returns/${returnNumber}`,
    );
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}
