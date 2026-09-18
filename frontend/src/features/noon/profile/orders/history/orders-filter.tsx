"use client";

import { Input } from "@/src/components/ui/base-inputs/input";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import useApiFilter from "@/src/hooks/useApiFilter";
import { orderStatusFilterOptions } from "../helpers/constants";
import { getOrderStatusLabelKey } from "../helpers/to-order-status";

export default function OrdersFilter() {
  const t = useTranslations("profile");
  const searchParams = useSearchParams();
  const { applyFilter } = useApiFilter();

  const currentStatus = searchParams.get("status") ?? undefined;

  const status = orderStatusFilterOptions.map((value) => ({
    label: t(getOrderStatusLabelKey(value)),
    value,
  }));

  return (
    <div className="flex items-center gap-3">
      <Input
        startIcon={<Search />}
        placeholder={t("findItems")}
        className="w-[246px]! rounded-none h-12 bg-white!"
      />

      <Select
        value={currentStatus ?? null}
        placeholder={t("orderStatusFilterPlaceholder")}
        onValueChange={(value) =>
          applyFilter({
            filterBy: "status",
            query: value ?? "",
          })
        }
        triggerClass="w-[230px]! rounded-none h-12! bg-white! justify-between"
        items={status}
      />
    </div>
  );
}
