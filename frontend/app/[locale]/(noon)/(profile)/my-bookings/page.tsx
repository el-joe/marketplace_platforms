import BookingsList from "@/src/features/flights/bookings-list";
import { getMyBookings } from "@/src/features/flights/api/bookings.actions";

export default async function MyBookingsPage() {
  const { items } = await getMyBookings();

  return <BookingsList data={items} />;
}
