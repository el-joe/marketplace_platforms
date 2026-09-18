"use client";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { FieldLabel } from "@/src/components/ui/field";
import { CircleQuestionMarkIcon, DoorOpenIcon, TreesIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { IPrepareCheckout } from "./types/checkout.type";
import useLocale from "@/src/hooks/use-locale";

export default function DeliveryInstructionsCard({
  instructions,
  selectedInstruction,
  onSelect,
}: {
  instructions: IPrepareCheckout["delivery_instructions"];
  selectedInstruction: string | null;
  onSelect: (value: string | null) => void;
}) {
  const t = useTranslations("checkout");
  const locale = useLocale();
  if (!instructions || !instructions.length) {
    return null;
  }

  return (
    <div className="p-3 rounded-2xl bg-white flex-1">
      <h4 className="text-base font-semibold mb-2">
        {t("deliveryInstructions")}
      </h4>
      <div className="flex gap-3">
        {instructions.map((s) => (
          <FieldLabel
            key={s.key}
            className="flex items-center flex-1 p-3 bg-gray-2 rounded-lg gap-3 cursor-pointer"
          >
            {s.key === "get_items_together" ? (
              <TreesIcon />
            ) : s.key === "leave_at_door" ? (
              <DoorOpenIcon />
            ) : (
              <CircleQuestionMarkIcon />
            )}
            <p className="text-gray text-sm">{s.value[locale]}</p>
            <Checkbox
              checked={selectedInstruction === s.key}
              onCheckedChange={(checked) => onSelect(checked ? s.key : null)}
            />
          </FieldLabel>
        ))}
      </div>
    </div>
  );
}
