"use client";

import { useTranslations } from "next-intl";
import { MinusIcon, PlusIcon } from "lucide-react";

type Props = {
  quantity: number;
  onChange: (quantity: number) => void;
};

export default function QuantitySelector({ quantity, onChange }: Props) {
  const t = useTranslations("giftCards");

  return (
    <div>
      <p className="font-medium mb-2">{t("selectQuantity")}</p>
      <div className="flex justify-between items-center rounded-lg border border-input w-[120px] px-3 py-1.5">
        <button
          type="button"
          aria-label="decrease"
          onClick={() => onChange(Math.max(1, quantity - 1))}
          className="cursor-pointer text-gray"
        >
          <MinusIcon className="size-4" />
        </button>
        <span className="w-4 text-center">{quantity}</span>
        <button
          type="button"
          aria-label="increase"
          onClick={() => onChange(quantity + 1)}
          className="cursor-pointer text-gray"
        >
          <PlusIcon className="size-4" />
        </button>
      </div>
    </div>
  );
}
