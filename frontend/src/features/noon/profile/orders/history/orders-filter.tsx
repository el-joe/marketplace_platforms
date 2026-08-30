"use client";

import { Input } from "@/src/components/ui/base-inputs/input";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import useApiFilter from "@/src/hooks/useApiFilter";

const TARGET_ENDPOINT = "orders";

export default function OrdersFilter() {
  const t = useTranslations("profile");
  const searchParams = useSearchParams();
  const { applyFilter } = useApiFilter();

  const currentStatus =
    searchParams.get(`filter_${TARGET_ENDPOINT}_status`) ?? undefined;

  const status = [
    { label: t("placed"), value: "placed" },
    { label: t("confirmed"), value: "confirmed" },
    { label: t("partiallyShipped"), value: "partially_shipped" },
    { label: t("shipped"), value: "shipped" },
    { label: t("partiallyDelivered"), value: "partially_delivered" },
    { label: t("delivered"), value: "delivered" },
    { label: t("completed"), value: "completed" },
    { label: t("cancelled"), value: "cancelled" },
    { label: t("refunded"), value: "refunded" },
    { label: t("disputed"), value: "disputed" },
  ];

  return (
    <div className="flex items-center gap-3">
      <Input
        startIcon={<Search />}
        placeholder={t("findItems")}
        className="w-[246px]! rounded-none h-12 bg-white!"
      />

      <Select
        value={currentStatus ?? null}
        placeholder={t("status")}
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
