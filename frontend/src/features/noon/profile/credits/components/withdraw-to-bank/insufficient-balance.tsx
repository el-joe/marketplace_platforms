"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";

type Props = {
  minWithdrawalAmount: string;
  onClose: () => void;
};

export default function InsufficientBalance({ minWithdrawalAmount, onClose }: Props) {
  const t = useTranslations("profile");

  return (
    <>
      <div className="flex flex-col items-center text-center px-2 pt-2 p-6 lg:mx-19">
        <Image
          src="/images/credits-withdrawal-not-allowed.svg"
          alt={t("insufficientBalanceTitle")}
          width={200}
          height={114}
        />

        <h3 className="mt-6 text-lg font-bold text-light">
          {t("insufficientBalanceTitle")}
        </h3>

        <p className="mt-2 text-sm text-gray leading-relaxed">
          {t("insufficientBalanceMessage", { amount: minWithdrawalAmount })}
        </p>
      </div>

      <div className="flex justify-end border-t border-border p-4">
        <Button
          onClick={onClose}
          variant="outline"
          className="text-blue-3 font-semibold rounded-none h-12 px-4 uppercase"
        >
          {t("close")}
        </Button>
      </div>
    </>
  );
}
