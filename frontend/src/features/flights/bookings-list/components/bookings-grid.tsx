import { useTranslations } from "next-intl";
import BookingCard from "./booking-card";
import type { TravelBooking } from "../../helpers/types";

type Props = {
  data: TravelBooking[];
  header: React.ReactNode;
};

export default function BookingsGrid({ data, header }: Props) {
  const t = useTranslations("flights");

  return (
    <div>
      {header}

      {data.length === 0 ? (
        <p className="mt-6 text-center text-sm text-gray">
          {t("myBookings.noResults")}
        </p>
      ) : (
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {data.map((booking) => (
            <BookingCard key={booking.id} booking={booking} />
          ))}
        </div>
      )}
    </div>
  );
}
