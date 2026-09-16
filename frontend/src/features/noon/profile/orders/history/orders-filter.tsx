"use client";

import { Input } from "@/src/components/ui/base-inputs/input";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import useApiFilter from "@/src/hooks/useApiFilter";
import { orderStatusFilterOptions } from "../helpers/constants";
import { getOrderStatusLabelKey } from "../helpers/to-order-status";

const TARGET_ENDPOINT = "orders";

export default function OrdersFilter() {
  const t = useTranslations("profile");
  const searchParams = useSearchParams();
  const { applyFilter } = useApiFilter();

  const currentStatus =
    searchParams.get(`filter_${TARGET_ENDPOINT}_status`) ?? undefined;

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
            targetEndpoint: TARGET_ENDPOINT,
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
