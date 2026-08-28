import { fetchInstance } from "@/src/lib/utils";

type ApiEnvelope<T> = { success: boolean; data: T };

export type MyTravelBooking = {
  id: string;
  booking_number: string;
  package_title: string;
  status: "pending" | "confirmed" | "cancelled" | "completed";
  departure_date: string | null;
  return_date: string | null;
  total_price: number;
  currency: string;
  created_at: string;
};

/** GET /account/travel-bookings */
export async function getMyTravelBookings(page = 1): Promise<{
  items: MyTravelBooking[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const envelope = await fetchInstance<ApiEnvelope<{
    items: MyTravelBooking[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }>>(`/account/travel-bookings?page=${page}`);
  return envelope.data;
}

/** GET /account/travel-bookings/:id */
export async function getMyTravelBookingDetail(id: string): Promise<MyTravelBooking> {
  const envelope = await fetchInstance<ApiEnvelope<MyTravelBooking>>(
    `/account/travel-bookings/${id}`,
  );
  return envelope.data;
}

/** POST /account/travel-bookings/:id/cancel */
export async function cancelTravelBooking(id: string): Promise<void> {
  await fetchInstance(`/account/travel-bookings/${id}/cancel`, {
    method: "POST",
  });
}
