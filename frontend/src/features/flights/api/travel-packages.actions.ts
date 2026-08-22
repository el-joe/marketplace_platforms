import { fetchInstance, ApiRequestError } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  ListTravelPackagesFilters,
  TravelPackageDetail,
  TravelPackagesListResponse,
} from "../helpers/types";

/** Feature-only: GET /browse/travel/:id — category-scoped ("all" by default) paginated travel package listing. */
export async function getTravelPackages(
  filters: ListTravelPackagesFilters = {},
): Promise<TravelPackagesListResponse> {
  const categoryId = filters.categoryId ?? "all";
  const params = new URLSearchParams();
  if (filters.page) params.set("page", String(filters.page));
  if (filters.perPage) params.set("per_page", String(filters.perPage));

  const query = params.toString();
  const envelope = await fetchInstance<ApiEnvelope<TravelPackagesListResponse>>(
    `/browse/travel/${categoryId}${query ? `?${query}` : ""}`,
  );
  return envelope.data;
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
