"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import Image from "next/image";
import { InfoIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Button } from "@/src/components/ui/button";
import { returnReasons } from "../../helpers/constants";
import { useCancelActions } from "../helpers/use-cancel-actions";
import type { OrderDetailItem, ReturnReason } from "../../helpers/types";

type Props = {
  orderNumber: string;
  items: OrderDetailItem[];
};

export default function CancelItemsForm({ orderNumber, items }: Props) {
  const t = useTranslations("profile");
  const locale = useLocale();
  const { cancelItems, isCancelling } = useCancelActions();

  const [selectedIds, setSelectedIds] = useState<Set<string>>(
    () => new Set(items.map((item) => item.id)),
  );
  const [reason, setReason] = useState<ReturnReason | null>(null);

  const toggleItem = (itemId: string, checked: boolean) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (checked) next.add(itemId);
      else next.delete(itemId);
      return next;
    });
  };

  const canSubmit = selectedIds.size > 0 && !!reason && !isCancelling;

  return (
    <Card className="border border-border p-6">
      <div className="divide-y divide-border">
        {items.map((item) => {
          const name =
            locale === "ar" && item.name_ar ? item.name_ar : item.name_en;

          return (
            <div key={item.id} className="flex items-start gap-4 py-4 first:pt-0">
              <Image
                src={item.thumbnail ?? "/images/profile/orders-icon.svg"}
                alt={name}
                width={72}
                height={72}
                className="size-18 shrink-0 rounded-lg object-cover"
              />
              <div className="min-w-0 flex-1">
                <p className="font-medium text-primary">{name}</p>
                <p className="mt-2 font-bold">
                  <Price
                    currentPrice={item.line_total}
                    size="sm"
                  />
                </p>
              </div>
              <Checkbox
                checked={selectedIds.has(item.id)}
                onCheckedChange={(value) => toggleItem(item.id, value === true)}
              />
            </div>
          );
        })}
      </div>

      <div className="mt-2 flex items-center justify-between border-t border-border pt-6">
        <p className="font-medium">{t("reasonForCancellation")}</p>
        <Select
          value={reason}
          onValueChange={(value) => setReason(value as ReturnReason)}
          triggerClass="w-[320px] h-11 justify-between"
          items={returnReasons.map((r) => ({
            label: t(r.labelKey),
            value: r.value,
          }))}
        />
      </div>

      <div className="mt-6 flex items-center justify-between border-t border-border pt-6">
        <p className="flex items-center gap-2 text-sm text-gray">
          <InfoIcon className="size-4 text-orange" />
          {t("cancellationCannotBeReversed")}
        </p>

        <Button
          disabled={!canSubmit}
          onClick={() =>
            reason && cancelItems(orderNumber, [...selectedIds], reason)
          }
          className="h-12 rounded-md bg-blue-3 px-6 font-semibold text-white"
        >
          {t(
            selectedIds.size > 1
              ? "cancelSelectedItemPlural"
              : "cancelSelectedItemSingular",
          )}
        </Button>
      </div>
    </Card>
  );
}
