"use client";

import { useTranslations } from "next-intl";
import { MinusIcon, PlusIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";

type Props = {
  quantity: number;
  min: number;
  max: number;
  onChange: (quantity: number) => void;
};

export default function QuantitySelector({
  quantity,
  min,
  max,
  onChange,
}: Props) {
  const t = useTranslations("giftCards");

  const canDecrease = quantity > min;
  const canIncrease = quantity < max;

  return (
    <div>
      <p className="font-medium mb-2">{t("selectQuantity")}</p>
      <div className="flex justify-between items-center rounded-lg border border-input w-[120px] px-3 py-1.5">
        <button
          type="button"
          aria-label="decrease"
          disabled={!canDecrease}
          onClick={() => onChange(quantity - 1)}
          className={cn(
            "text-gray",
            canDecrease ? "cursor-pointer" : "opacity-30 cursor-not-allowed",
          )}
        >
          <MinusIcon className="size-4" />
        </button>
        <span className="w-4 text-center">{quantity}</span>
        <button
          type="button"
          aria-label="increase"
          disabled={!canIncrease}
          onClick={() => onChange(quantity + 1)}
          className={cn(
            "text-gray",
            canIncrease ? "cursor-pointer" : "opacity-30 cursor-not-allowed",
          )}
        >
          <PlusIcon className="size-4" />
        </button>
      </div>
    </div>
  );
}
