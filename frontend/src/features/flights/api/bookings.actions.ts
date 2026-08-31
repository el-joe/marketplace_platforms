import { fetchInstance, ApiRequestError } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  BookingsListResponse,
  CancelBookingResult,
  ListBookingsFilters,
  TravelBookingDetail,
} from "../helpers/types";

type UploadPassportResult = { passport_uploaded: boolean };

/** Feature-only: GET /account/travel-bookings — paginated list of the customer's bookings. */
export async function getMyBookings(
  filters: ListBookingsFilters = {},
): Promise<BookingsListResponse> {
  const params = new URLSearchParams();
  if (filters.status) params.set("status", filters.status);
  if (filters.page) params.set("page", String(filters.page));

  const query = params.toString();
  const envelope = await fetchInstance<ApiEnvelope<BookingsListResponse>>(
    `/account/travel-bookings${query ? `?${query}` : ""}`,
  );
  return envelope.data;
}

/** Feature-only: GET /account/travel-bookings/:id — booking detail. Returns undefined on 404. */
export async function getMyBookingById(
  id: string,
): Promise<TravelBookingDetail | undefined> {
  try {
    const envelope = await fetchInstance<ApiEnvelope<TravelBookingDetail>>(
      `/account/travel-bookings/${id}`,
    );
    return envelope.data;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Feature-only: POST /account/travel-bookings/:id/cancel — cancels a booking. */
export async function cancelMyBooking(
  id: string,
  reason: string,
): Promise<CancelBookingResult> {
  const envelope = await fetchInstance<ApiEnvelope<CancelBookingResult>>(
    `/account/travel-bookings/${id}/cancel`,
    {
      method: "POST",
      body: JSON.stringify({ reason }),
    },
  );
  return envelope.data;
}

/** Feature-only: POST /account/travel-bookings/:id/passport — uploads a passport file for a booking. */
export async function uploadPassport(
  id: string,
  file: File,
): Promise<UploadPassportResult> {
  const formData = new FormData();
  formData.append("passport_file", file);

  const envelope = await fetchInstance<ApiEnvelope<UploadPassportResult>>(
    `/account/travel-bookings/${id}/passport`,
    {
      method: "POST",
      body: formData,
    },
  );
  return envelope.data;
}
