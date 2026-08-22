"use client";

import Image from "next/image";
import { ChevronRight } from "lucide-react";
import { useTranslations } from "next-intl";

type Props = {
  onSelectGiftcards: () => void;
};

export default function SelectMethod({ onSelectGiftcards }: Props) {
  const t = useTranslations("profile");

  return (
    <button
      onClick={onSelectGiftcards}
      className="w-full flex items-center justify-between gap-3 border border-border rounded-xl px-4 py-3 cursor-pointer hover:bg-muted/50"
    >
      <span className="flex items-center gap-3 font-medium">
        <Image
          src="/images/profile/redeem-giftcards-push.svg"
          alt={t("giftcardsAndVouchers")}
          width={36}
          height={36}
        />
        {t("giftcardsAndVouchers")}
      </span>
      <ChevronRight className="size-5 text-gray" />
    </button>
  );
}
