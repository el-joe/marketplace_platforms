"use client";

import { JSXElementConstructor, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { MessageCircleIcon } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { OtpInput } from "@/src/components/ui/otp-input";

const OTP_LENGTH = 6;

type Channel = "whatsapp" | "sms";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  phone: string;
  attemptsLeft?: number;
  resendSeconds?: number;
  onVerified?: (otp: string) => void;
};

export default function VerifyMobileDialog({
  trigger,
  phone,
  attemptsLeft = 7,
  resendSeconds = 30,
  onVerified,
}: Props) {
  const t = useTranslations("profile");
  const [open, setOpen] = useState(false);
  const [otp, setOtp] = useState("");
  const [channel, setChannel] = useState<Channel>("whatsapp");
  const [secondsLeft, setSecondsLeft] = useState(resendSeconds);

  const handleOpenChange = (nextOpen: boolean) => {
    setOpen(nextOpen);
    if (nextOpen) {
      setOtp("");
      setChannel("whatsapp");
      setSecondsLeft(resendSeconds);
    }
  };

  useEffect(() => {
    if (!open || secondsLeft <= 0) return;
    const timer = setTimeout(
      () => setSecondsLeft((seconds) => seconds - 1),
      1000,
    );
    return () => clearTimeout(timer);
  }, [open, secondsLeft]);

  const handleResend = (nextChannel: Channel) => {
    setChannel(nextChannel);
    setSecondsLeft(resendSeconds);
    setOtp("");
  };

  const handleVerify = () => {
    onVerified?.(otp);
    setOpen(false);
  };

  const minutes = Math.floor(secondsLeft / 60);
  const seconds = secondsLeft % 60;

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger render={trigger} />
      <DialogContent className="max-w-sm text-center" showCloseButton>
        <h2 className="text-xl font-bold">{t("verifyMobileTitle")}</h2>

        <p className="text-sm text-gray">
          {t("verifyMobileDescription", { phone })}
        </p>

        <p className="flex items-center justify-center gap-1.5 font-semibold text-green">
          <MessageCircleIcon className="size-4" />
          {t(channel === "whatsapp" ? "whatsapp" : "sms")}
        </p>

        <OtpInput
          length={OTP_LENGTH}
          value={otp}
          onChange={setOtp}
          className="justify-center"
        />

        <div className="text-sm text-gray">
          <p>{t("didntGetOtp")}</p>
          {secondsLeft > 0 && (
            <p>
              {t("resendOtpIn")} {minutes}:{seconds.toString().padStart(2, "0")}
            </p>
          )}
          <p>{t("attemptsLeft", { count: attemptsLeft })}</p>
        </div>

        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            disabled={secondsLeft > 0}
            onClick={() => handleResend("whatsapp")}
            className="flex-1 rounded-full"
          >
            {t("whatsapp")}
          </Button>
          <Button
            type="button"
            variant="outline"
            disabled={secondsLeft > 0}
            onClick={() => handleResend("sms")}
            className="flex-1 rounded-full"
          >
            {t("sms")}
          </Button>
        </div>

        <Button
          type="button"
          disabled={otp.length < OTP_LENGTH}
          onClick={handleVerify}
          className="h-12 w-full rounded-md bg-blue-3 font-semibold text-white uppercase disabled:opacity-50"
        >
          {t("verify")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
