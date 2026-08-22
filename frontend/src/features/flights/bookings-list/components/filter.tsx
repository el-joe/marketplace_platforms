import { useTranslations } from "next-intl";
import { Select } from "@/src/components/ui/base-inputs/select";
import { ALL, BOOKING_STATUSES } from "../helpers/constants";

type Props = {
  statusValue: string;
  onStatusChange: (value: string) => void;
};

export default function BookingsFilter({ statusValue, onStatusChange }: Props) {
  const t = useTranslations("flights");

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Select
        value={statusValue}
        onValueChange={(v) => onStatusChange(v ?? ALL)}
        placeholder={t("myBookings.filters.status")}
        triggerClass="w-[230px]! rounded-none h-12! bg-white! justify-between"
        items={[
          { label: t("myBookings.filters.allStatuses"), value: ALL },
          ...BOOKING_STATUSES.map((s) => ({
            label: t(`myBookings.status.${s}`),
            value: s,
          })),
        ]}
      />
    </div>
  );
}
