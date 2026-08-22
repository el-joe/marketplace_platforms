"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import Price from "@/src/components/shared/Price";

type Props = {
  quantity: number;
  totalAmount: number;
  disabled: boolean;
  isSubmitting: boolean;
  onSubmit: () => void;
};

export default function PriceSummary({
  quantity,
  totalAmount,
  disabled,
  isSubmitting,
  onSubmit,
}: Props) {
  const t = useTranslations("giftCards");

  return (
    <div>
      <div className="flex items-center justify-between text-gray text-[15px]">
        <p className="font-medium">
          {quantity} {t("giftCard")}
        </p>
        <Price currentPrice={totalAmount} size="lg" />
      </div>
      <Button
        disabled={disabled}
        onClick={onSubmit}
        className="w-full h-12 rounded-md bg-blue-3 font-semibold text-white uppercase disabled:opacity-50"
      >
        {isSubmitting ? t("purchasing") : t("proceedToBuy")}
      </Button>
    </div>
  );
}
