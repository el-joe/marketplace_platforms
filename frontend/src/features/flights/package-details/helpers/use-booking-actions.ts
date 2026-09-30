"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { createBooking } from "../../api/bookings.actions";
import { ApiRequestError } from "@/src/lib/utils";

type UnitSelection = { unit_id: string; unit_days: { date: string; includes_overnight?: boolean; time_slot_id?: string }[] } | null;

export function useBookingActions(slug: string) {
  const t = useTranslations("flights.packageDetails");
  const router = useRouter();
  const [isBooking, setIsBooking] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const book = async (travelersCount: number, unitSelection: UnitSelection = null) => {
    setIsBooking(true);
    setError(null);
    try {
      const booking = await createBooking(slug, travelersCount, unitSelection);
      router.push(`/my-bookings/${booking.id}`);
      return true;
    } catch (err: unknown) {
      const msg =
        err instanceof ApiRequestError
          ? err.message
          : err instanceof Error
            ? err.message
            : t("bookingError");
      setError(msg);
      return false;
    } finally {
      setIsBooking(false);
    }
  };

  return { book, isBooking, error };
}
