import { notFound } from "next/navigation";
import BookingDetails from "@/src/features/flights/booking-details";
import { getMyBookingById } from "@/src/features/flights/api/bookings.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function MyBookingDetailsPage({ params }: Props) {
  const { id } = await params;
  const booking = await getMyBookingById(id);

  if (!booking) notFound();

  return <BookingDetails booking={booking} />;
}
