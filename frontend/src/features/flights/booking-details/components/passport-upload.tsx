"use client";

import { useRef } from "react";
import { useTranslations } from "next-intl";
import { UploadIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { usePassportUploadActions } from "../helpers/use-passport-upload-actions";

type Props = {
  bookingId: string;
};

export default function PassportUpload({ bookingId }: Props) {
  const t = useTranslations("flights");
  const inputRef = useRef<HTMLInputElement>(null);
  const { uploadBookingPassport, isUploading } = usePassportUploadActions(bookingId);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    await uploadBookingPassport(file);
  };

  return (
    <div className="flex flex-col gap-1.5">
      <input
        ref={inputRef}
        type="file"
        accept=".pdf,.jpg,.jpeg,.png"
        className="hidden"
        onChange={handleFileChange}
        disabled={isUploading}
      />
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="w-fit"
        disabled={isUploading}
        onClick={() => inputRef.current?.click()}
      >
        <UploadIcon className="size-4" />
        {isUploading ? t("myBookings.uploading") : t("myBookings.uploadPassport")}
      </Button>
      <p className="text-xs text-gray">{t("myBookings.passportHint")}</p>
    </div>
  );
}
