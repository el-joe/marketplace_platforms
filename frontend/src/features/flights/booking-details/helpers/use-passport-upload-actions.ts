"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { useRouter } from "@/i18n/navigation";
import { uploadPassport } from "../../api/bookings.actions";

/**
 * Feature-level action for the booking-details page — uploads the passport
 * file (POST /account/travel-bookings/:id/passport).
 */
export function usePassportUploadActions(bookingId: string) {
  const t = useTranslations("flights");
  const router = useRouter();
  const [isUploading, setIsUploading] = useState(false);

  const uploadBookingPassport = async (file: File) => {
    setIsUploading(true);
    try {
      await uploadPassport(bookingId, file);
      toast.success(t("myBookings.passportUploadedSuccess"));
      router.refresh();
      return true;
    } catch {
      toast.error(t("myBookings.uploadError"));
      return false;
    } finally {
      setIsUploading(false);
    }
  };

  return { uploadBookingPassport, isUploading };
}
