"use client";

import { createContext, useContext } from "react";
import type { useUnitBooking } from "./use-unit-booking";

type UnitBookingContextValue = ReturnType<typeof useUnitBooking>;

export const UnitBookingContext = createContext<UnitBookingContextValue | null>(null);

export function useUnitBookingContext(): UnitBookingContextValue {
  const ctx = useContext(UnitBookingContext);
  if (!ctx) throw new Error("useUnitBookingContext must be used inside UnitBookingProvider");
  return ctx;
}
