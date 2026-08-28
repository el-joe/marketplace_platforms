"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import {
  redeemGiftCard,
  redeemVoucher,
} from "../api/gift-card-wallet.actions";

type Params = {
  onSuccess: () => void;
};

export function useRedeemActions({ onSuccess }: Params) {
  const t = useTranslations("profile");
  const router = useRouter();

  const [giftCardNumber, setGiftCardNumber] = useState("");
  const [pin, setPin] = useState("");
  const [voucherCode, setVoucherCode] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleError = (error: unknown) => {
    const message =
      error instanceof ApiRequestError ? error.message : t("redeemFailed");
    toast.error(message);
  };

  const submitGiftCard = async () => {
    setIsSubmitting(true);
    try {
      await redeemGiftCard({ code: giftCardNumber, pin });
      toast.success(t("giftCardRedeemed"));
      setGiftCardNumber("");
      setPin("");
      router.refresh();
      onSuccess();
    } catch (error) {
      handleError(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  const submitVoucher = async () => {
    setIsSubmitting(true);
    try {
      await redeemVoucher({ code: voucherCode });
      toast.success(t("voucherRedeemed"));
      setVoucherCode("");
      router.refresh();
      onSuccess();
    } catch (error) {
      handleError(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return {
    giftCardNumber,
    setGiftCardNumber,
    pin,
    setPin,
    voucherCode,
    setVoucherCode,
    isSubmitting,
    submitGiftCard,
    submitVoucher,
  };
}
