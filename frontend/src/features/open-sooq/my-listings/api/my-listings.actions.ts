import { fetchInstance } from "@/src/lib/utils";

type ApiEnvelope<T> = { success: boolean; data: T };

export type MyClassifiedListing = {
  id: string;
  listing_number: string;
  title: string;
  status: "draft" | "pending_contract" | "pending_review" | "active" | "paused" | "sold" | "expired" | "rejected";
  price: number;
  currency: string;
  created_at: string;
  images: { url: string }[];
};

/** GET /account/classified-listings */
export async function getMyClassifiedListings(
  filters: { status?: string; page?: number } = {},
): Promise<{
  items: MyClassifiedListing[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const params = new URLSearchParams();
  if (filters.status) params.set("status", filters.status);
  if (filters.page) params.set("page", String(filters.page));
  const envelope = await fetchInstance<ApiEnvelope<{
    items: MyClassifiedListing[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }>>(`/account/classified-listings?${params.toString()}`);
  return envelope.data;
}

/** GET /account/classified-listings/:listing_number */
export async function getMyClassifiedListingDetail(listingNumber: string): Promise<MyClassifiedListing> {
  const envelope = await fetchInstance<ApiEnvelope<MyClassifiedListing>>(
    `/account/classified-listings/${listingNumber}`,
  );
  return envelope.data;
}

/** PUT /account/classified-listings/:listing_number */
export async function updateMyClassifiedListing(
  listingNumber: string,
  payload: Partial<MyClassifiedListing>,
): Promise<MyClassifiedListing> {
  const envelope = await fetchInstance<ApiEnvelope<MyClassifiedListing>>(
    `/account/classified-listings/${listingNumber}`,
    { method: "PUT", body: JSON.stringify(payload) },
  );
  return envelope.data;
}

/** DELETE /account/classified-listings/:listing_number */
export async function deleteMyClassifiedListing(listingNumber: string): Promise<void> {
  await fetchInstance(`/account/classified-listings/${listingNumber}`, {
    method: "DELETE",
  });
}
