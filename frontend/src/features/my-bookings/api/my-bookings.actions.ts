import { fetchInstance } from "@/src/lib/utils";
import type { UnifiedBooking } from "../helpers/types";

type ApiEnvelope<T> = { success: boolean; message?: string; data: T };

/** GET /my-bookings — unified list across travel packages, bookable units and flights. */
export async function getUnifiedBookings(): Promise<UnifiedBooking[]> {
  const envelope =
    await fetchInstance<ApiEnvelope<UnifiedBooking[]>>("/my-bookings");
  return envelope.data;
}
