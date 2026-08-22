import { fetchInstance } from "@/src/lib/utils";

import type {
  ApiEnvelope,
  ClassifiedBrowseFilters,
  ClassifiedBrowseResponse,
} from "../helpers/types";

export async function getClassifiedBrowse(
  categoryId: string,
  filters: ClassifiedBrowseFilters = {},
): Promise<ClassifiedBrowseResponse> {
  const params = new URLSearchParams();
  if (filters.page) params.set("page", String(filters.page));
  if (filters.per_page) params.set("per_page", String(filters.per_page));
  if (filters.min_price !== undefined)
    params.set("min_price", String(filters.min_price));
  if (filters.max_price !== undefined)
    params.set("max_price", String(filters.max_price));
  if (filters.listing_purpose)
    params.set("listing_purpose", filters.listing_purpose);
  if (filters.seller_type) params.set("seller_type", filters.seller_type);

  const query = params.toString();
  const envelope = await fetchInstance<ApiEnvelope<ClassifiedBrowseResponse>>(
    `/browse/classified/${categoryId}${query ? `?${query}` : ""}`,
  );
  return envelope.data;
}
