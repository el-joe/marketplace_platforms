"use client";

import { useLocale, useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";
import type { PaymentOption } from "../../helpers/types";

type Props = {
  isLoading: boolean;
  options: PaymentOption[];
  selectedId: string;
  onSelect: (id: string) => void;
  error?: string;
};

export default function PaymentMethodSelector({
  isLoading,
  options,
  selectedId,
  onSelect,
  error,
}: Props) {
  const t = useTranslations("giftCards");
  const locale = useLocale();

  return (
    <div>
      <p className="font-medium mb-2">{t("selectPaymentMethod")}</p>

      {isLoading && (
        <p className="text-sm text-gray">{t("loadingPaymentMethods")}</p>
      )}

      {!isLoading && options.length === 0 && (
        <p className="text-sm text-red-500">{t("noPaymentMethodsAvailable")}</p>
      )}

      {!isLoading && options.length > 0 && (
        <div className="flex flex-col gap-2">
          {options.map((option) => {
            const label =
              locale === "ar" ? option.display_name.ar : option.display_name.en;
            const disabled = !option.is_available;

            return (
              <label
                key={option.id}
                className={cn(
                  "flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm font-medium",
                  disabled
                    ? "cursor-not-allowed opacity-50 border-input"
                    : "cursor-pointer",
                  !disabled && selectedId === option.id
                    ? "border-blue-3 text-blue-3 bg-blue-3/5"
                    : "border-input",
                )}
              >
                <input
                  type="radio"
                  name="payment_method"
                  className="accent-blue-3"
                  checked={selectedId === option.id}
                  disabled={disabled}
                  onChange={() => onSelect(option.id)}
                />
                <span>{label || option.gateway_code}</span>
                {disabled && option.unavailable_reason && (
                  <span className="text-xs text-gray">
                    ({option.unavailable_reason})
                  </span>
                )}
              </label>
            );
          })}
        </div>
      )}

      {error && <p className="text-sm text-red-500 mt-1">{error}</p>}
    </div>
  );
}
