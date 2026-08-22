import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { FieldLabel } from "@/src/components/ui/field";
import { CircleQuestionMarkIcon, DoorOpenIcon, TreesIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";

export default function DeliveryInstructionsCard({
  instructions,
}: {
  instructions: IPrepareCheckout["delivery_instructions"];
}) {
  const t = useTranslations("checkout");
  if (!instructions.length) {
    return;
  }
  return (
    <div className="p-3 rounded-2xl bg-white flex-1">
      <h4 className="text-base font-semibold mb-2">
        {t("deliveryInstructions")}
      </h4>
      <div className="flex gap-3">
        {instructions?.map((s) => (
          <FieldLabel
            key={s.value}
            className="flex items-center flex-1 p-3 bg-gray-2 rounded-lg gap-3"
          >
            {s.value === "get_items_together" ? (
              <TreesIcon />
            ) : s.value === "leave_at_door" ? (
              <DoorOpenIcon />
            ) : (
              <CircleQuestionMarkIcon />
            )}
            <p className="text-gray text-sm">{s.label}</p>
            <Checkbox />
          </FieldLabel>
        ))}
      </div>
    </div>
  );
}
