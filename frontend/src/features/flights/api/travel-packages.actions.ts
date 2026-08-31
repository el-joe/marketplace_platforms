import { fetchInstance, ApiRequestError } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  ListTravelPackagesFilters,
  TravelPackageDetail,
  TravelPackagesListResponse,
} from "../helpers/types";

/** Feature-only: GET /browse/travel/:id — category-scoped ("all" by default) paginated travel package listing. Returns undefined on 404 (unknown category). */
export async function getTravelPackages(
  filters: ListTravelPackagesFilters = {},
): Promise<TravelPackagesListResponse | undefined> {
  const categoryId = filters.categoryId ?? "all";
  const params = new URLSearchParams();
  if (filters.page) params.set("page", String(filters.page));
  if (filters.perPage) params.set("per_page", String(filters.perPage));

  const query = params.toString();
  try {
    const envelope = await fetchInstance<
      ApiEnvelope<TravelPackagesListResponse>
    >(`/browse/travel/${categoryId}${query ? `?${query}` : ""}`);
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Feature-only: GET /listings/travel/:slug — travel package detail. Returns undefined on 404. */
export async function getTravelPackageBySlug(
  slug: string,
): Promise<TravelPackageDetail | undefined> {
  try {
    const envelope = await fetchInstance<ApiEnvelope<TravelPackageDetail>>(
      `/listings/travel/${slug}`,
    );
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** POST /listings/travel/:slug/inquiries — submit a contact inquiry for a travel package. Guest-accessible (no auth required). */
export async function submitTravelInquiry(
  slug: string,
  payload: {
    name: string;
    email: string;
    message: string;
    phone?: string;
    travelers_count?: number;
  },
): Promise<void> {
  await fetchInstance<ApiEnvelope<null>>(`/listings/travel/${slug}/inquiries`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}
