"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { createBooking } from "../../api/bookings.actions";
import { ApiRequestError } from "@/src/lib/utils";

/**
 * Feature-level action for the package-details booking sidebar — creates a
 * real multi-seat booking (POST /listings/travel/:slug/bookings) and
 * redirects to the booking's confirmation/detail page on success.
 */
export function useBookingActions(slug: string) {
  const t = useTranslations("flights.packageDetails");
  const router = useRouter();
  const [isBooking, setIsBooking] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const book = async (travelersCount: number) => {
    setIsBooking(true);
    setError(null);
    try {
      const booking = await createBooking(slug, travelersCount);
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
