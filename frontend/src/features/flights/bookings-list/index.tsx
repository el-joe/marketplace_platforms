"use client";

import { useTranslations } from "next-intl";
import BookingsFilter from "./components/filter";
import BookingsGrid from "./components/bookings-grid";
import { useBookingsList } from "./helpers/use-bookings-list";
import type { TravelBooking } from "../helpers/types";

type Props = {
  data: TravelBooking[];
};

export default function BookingsList({ data }: Props) {
  const t = useTranslations("flights");
  const { status, setStatus, filtered } = useBookingsList(data);

  return (
    <div>
      <h1 className="text-[28px] font-bold text-primary">
        {t("myBookings.title")}
      </h1>
      <p className="text-sm text-gray mt-1">{t("myBookings.subtitle")}</p>

      <BookingsGrid
        data={filtered}
        header={
          <div className="mt-6">
            <BookingsFilter statusValue={status} onStatusChange={setStatus} />
          </div>
        }
      />
    </div>
  );
}
