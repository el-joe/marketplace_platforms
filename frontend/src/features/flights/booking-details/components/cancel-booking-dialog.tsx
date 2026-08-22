"use client";

import { useId, useState } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { Textarea } from "@/src/components/ui/base-inputs/textarea";
import { useCancelBookingActions } from "../helpers/use-cancel-booking-actions";

type Props = {
  bookingId: string;
};

const MAX_REASON_LENGTH = 500;

export default function CancelBookingDialog({ bookingId }: Props) {
  const t = useTranslations("flights");
  const { cancelBooking, isCancelling } = useCancelBookingActions(bookingId);

  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const textareaId = useId();

  const handleOpenChange = (next: boolean) => {
    setOpen(next);
    if (!next) {
      setReason("");
      setError(null);
    }
  };

  const handleConfirm = async () => {
    const trimmed = reason.trim();
    if (!trimmed) {
      setError(t("myBookings.cancelReasonRequired"));
      return;
    }
    if (trimmed.length > MAX_REASON_LENGTH) {
      setError(
        t("myBookings.cancelReasonTooLong", { max: MAX_REASON_LENGTH }),
      );
      return;
    }

    const cancelled = await cancelBooking(trimmed);
    if (cancelled) handleOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger
        render={
          <Button variant="destructive" className="font-semibold uppercase" />
        }
      >
        {t("myBookings.cancelBooking")}
      </DialogTrigger>

      <DialogContent className="max-w-[440px]">
        <DialogHeader>
          <DialogTitle className="text-red">
            {t("myBookings.cancelBookingConfirmTitle")}
          </DialogTitle>
          <DialogDescription>
            {t("myBookings.cancelBookingConfirmDescription")}
          </DialogDescription>
        </DialogHeader>

        <Textarea
          id={textareaId}
          label={t("myBookings.cancelReasonLabel")}
          rows={3}
          value={reason}
          onChange={(e) => {
            setReason(e.target.value);
            if (error) setError(null);
          }}
          aria-invalid={!!error}
          disabled={isCancelling}
        />
        {error && <p className="text-xs text-destructive">{error}</p>}

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={isCancelling}
          >
            {t("myBookings.cancelBookingCancelButton")}
          </Button>
          <Button
            variant="destructive"
            onClick={handleConfirm}
            disabled={isCancelling}
          >
            {isCancelling
              ? t("myBookings.cancelling")
              : t("myBookings.cancelBookingConfirmButton")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
