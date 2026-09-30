"use client";

import { UnitBookingContext } from "../helpers/unit-booking-context";
import { useUnitBooking } from "../helpers/use-unit-booking";
import type { BookableUnitSummary } from "../../helpers/types";

type Props = {
  units: BookableUnitSummary[];
  children: React.ReactNode;
};

export default function UnitBookingProvider({ units, children }: Props) {
  const booking = useUnitBooking(units);
  return (
    <UnitBookingContext.Provider value={booking}>
      {children}
    </UnitBookingContext.Provider>
  );
}
