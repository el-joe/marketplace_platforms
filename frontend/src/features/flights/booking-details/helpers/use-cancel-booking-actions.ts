"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { useRouter } from "@/i18n/navigation";
import { cancelMyBooking } from "../../api/bookings.actions";

/**
 * Feature-level action for the booking-details page — cancels the booking
 * (POST /account/travel-bookings/:id/cancel) with the customer's reason.
 */
export function useCancelBookingActions(bookingId: string) {
  const t = useTranslations("flights");
  const router = useRouter();
  const [isCancelling, setIsCancelling] = useState(false);

  const cancelBooking = async (reason: string) => {
    setIsCancelling(true);
    try {
      await cancelMyBooking(bookingId, reason);
      toast.success(t("myBookings.cancelSuccess"));
      router.refresh();
      return true;
    } catch {
      toast.error(t("myBookings.cancelFailed"));
      return false;
    } finally {
      setIsCancelling(false);
    }
  };

  return { cancelBooking, isCancelling };
}
