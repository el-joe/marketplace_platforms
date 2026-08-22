"use client";

import { ChevronDownIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";

import Dropdown from "@/src/components/shared/Dropdown";
import { Button } from "@/src/components/ui/button";
import useApiFilter from "@/src/hooks/useApiFilter";

const TARGET_ENDPOINT = "products";

const sortOptions = [
  { itemLabel: "Recommended", value: "recommended" },
  { itemLabel: "Newest", value: "newest" },
  { itemLabel: "Price: Low to High", value: "price_asc" },
  { itemLabel: "Price: High to Low", value: "price_desc" },
  { itemLabel: "Top Rated", value: "top_rated" },
];

const SortSelect = () => {
  const t = useTranslations("shop");
  const searchParams = useSearchParams();
  const { applyFilter } = useApiFilter();

  const currentSort =
    searchParams.get(`filter_${TARGET_ENDPOINT}_sort`) ?? sortOptions[0].value;
  const currentLabel =
    sortOptions.find((option) => option.value === currentSort)?.itemLabel ??
    sortOptions[0].itemLabel;

  return (
    <Dropdown
      listTitle={t("sortBy")}
      items={sortOptions}
      onSelect={(item) =>
        applyFilter({
          targetEndpoint: TARGET_ENDPOINT,
          filterBy: "sort",
          query: item.value,
        })
      }
      triggerButton={
        <Button variant="outline" className="gap-1.5">
          <span className="text-gray">{t("sortBy")}:</span>
          <span>{currentLabel}</span>
          <ChevronDownIcon className="size-4" />
        </Button>
      }
    />
  );
};

export default SortSelect;
