import { useTranslations } from "next-intl";
import { Select } from "@/src/components/ui/base-inputs/select";
import { ALL } from "./constants";

type Props = {
  statusValue: string;
  onStatusChange: (value: string) => void;
  statusOptions: string[];
  secondaryValue: string;
  onSecondaryChange: (value: string) => void;
  secondaryOptions: string[];
  secondaryFilterKey: "priority" | "reason";
};

export default function CaseListFilter({
  statusValue,
  statusOptions,
  secondaryValue,
  secondaryOptions,
  secondaryFilterKey,
  onStatusChange,
  onSecondaryChange,
}: Props) {
  const t = useTranslations("support");

  const allSecondaryLabel =
    secondaryFilterKey === "priority"
      ? t("filters.allPriorities")
      : t("filters.allReasons");

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Select
        value={statusValue}
        onValueChange={(v) => onStatusChange(v ?? ALL)}
        placeholder={t("filters.status")}
        triggerClass="w-[230px]! rounded-none h-12! bg-white! justify-between"
        items={[
          { label: t("filters.allStatuses"), value: ALL },
          ...statusOptions.map((s) => ({ label: t(`status.${s}`), value: s })),
        ]}
      />
      <Select
        value={secondaryValue}
        onValueChange={(v) => onSecondaryChange(v ?? ALL)}
        placeholder={t(`filters.${secondaryFilterKey}`)}
        triggerClass="w-[230px]! rounded-none h-12! bg-white! justify-between"
        items={[
          { label: allSecondaryLabel, value: ALL },
          ...secondaryOptions.map((s) => ({
            label: t(`${secondaryFilterKey}.${s}`),
            value: s,
          })),
        ]}
      />
    </div>
  );
}
