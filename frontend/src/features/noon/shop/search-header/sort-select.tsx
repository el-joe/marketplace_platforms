"use client";

import { ChevronDownIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";

import Dropdown from "@/src/components/shared/Dropdown";
import { Button } from "@/src/components/ui/button";
import useShopFilterParams from "@/src/features/noon/shop/filter/helpers/use-shop-filter-params";

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
  const { setFilter } = useShopFilterParams();

  const currentSort = searchParams.get("sort") ?? sortOptions[0].value;
  const currentLabel =
    sortOptions.find((option) => option.value === currentSort)?.itemLabel ??
    sortOptions[0].itemLabel;

  return (
    <Dropdown
      listTitle={t("sortBy")}
      items={sortOptions}
      onSelect={(item) => setFilter("sort", item.value)}
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
