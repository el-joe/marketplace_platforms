"use client";

import { useTranslations } from "next-intl";
import { InfoIcon } from "lucide-react";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { Input } from "@/src/components/ui/base-inputs/input";

type Props = {
  buyingForMyself: boolean;
  onBuyingForMyselfChange: (value: boolean) => void;
  receiverName: string;
  onReceiverNameChange: (value: string) => void;
  receiverEmail: string;
  onReceiverEmailChange: (value: string) => void;
};

export default function ReceiverForm({
  buyingForMyself,
  onBuyingForMyselfChange,
  receiverName,
  onReceiverNameChange,
  receiverEmail,
  onReceiverEmailChange,
}: Props) {
  const t = useTranslations("giftCards");

  return (
    <div className="flex flex-col gap-4">
      <Checkbox
        label={t("buyingForMyself")}
        checked={buyingForMyself}
        onCheckedChange={(value) => onBuyingForMyselfChange(value === true)}
      />

      <Input
        label={t("receiversName")}
        placeholder={t("receiversName")}
        disabled={buyingForMyself}
        value={receiverName}
        onChange={(e) => onReceiverNameChange(e.target.value)}
      />

      <Input
        label={t("receiversEmail")}
        type="email"
        placeholder={t("receiversEmail")}
        disabled={buyingForMyself}
        value={receiverEmail}
        onChange={(e) => onReceiverEmailChange(e.target.value)}
      />

      <p className="flex items-center gap-2 text-xs text-gray">
        <InfoIcon className="size-4 shrink-0" />
        {t("validityNotice")}
      </p>
    </div>
  );
}
