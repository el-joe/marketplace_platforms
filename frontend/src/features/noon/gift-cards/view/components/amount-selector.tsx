"use client";

import { useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";
import { getCurrencySymbol } from "@/src/helpers/get-currency-symbol";
import { giftCardAmounts } from "../../data";

type Props = {
  amount: number;
  onSelect: (amount: number) => void;
};

export default function AmountSelector({ amount, onSelect }: Props) {
  const t = useTranslations("giftCards");

  return (
    <div>
      <p className="font-medium mb-2 flex items-center gap-1">
        {t("selectAmount")} ({getCurrencySymbol("AED")})
      </p>
      <div className="flex items-center gap-2 flex-wrap text-light">
        {giftCardAmounts.map((value) => (
          <button
            key={value}
            type="button"
            onClick={() => onSelect(value)}
            className={cn(
              "h-10 min-w-14 rounded-lg border px-3 text-sm font-medium cursor-pointer",
              amount === value
                ? "border-blue-3 text-blue-3 bg-blue-3/5"
                : "border-input",
            )}
          >
            {value}
          </button>
        ))}
      </div>
    </div>
  );
}
