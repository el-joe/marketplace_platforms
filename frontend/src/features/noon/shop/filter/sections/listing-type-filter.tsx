"use client";

import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import useShopFilterParams from "@/src/features/noon/shop/filter/helpers/use-shop-filter-params";
import FilterAccordionSection from "./filter-accordion-section";

type ListingTypeOption = {
  value: string;
  labelKey: string;
};

const OPTIONS: ListingTypeOption[] = [
  { value: "", labelKey: "listingSourceAll" },
  { value: "admin_listing", labelKey: "listingSourceAdmin" },
  { value: "vendor_listing", labelKey: "listingSourceVendor" },
  { value: "marketer_listing", labelKey: "listingSourceMarketer" },
];

const ListingTypeFilter = () => {
  const t = useTranslations("shop");
  const searchParams = useSearchParams();
  const { setFilter } = useShopFilterParams();

  const current = searchParams.get("type") ?? "";

  return (
    <FilterAccordionSection value="listing_source" title={t("listingSource")}>
      <div className="flex flex-col gap-1.5 py-1">
        {OPTIONS.map((opt) => {
          const isSelected = current === opt.value;
          return (
            <button
              key={opt.value || "all"}
              onClick={() => setFilter("type", opt.value)}
              className={[
                "flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm transition-colors",
                isSelected
                  ? "bg-primary/10 font-semibold text-primary"
                  : "text-primary hover:bg-gray-2",
              ].join(" ")}
              aria-pressed={isSelected}
            >
              <span
                className={[
                  "inline-flex h-4 w-4 shrink-0 rounded-full border-2 items-center justify-center",
                  isSelected ? "border-primary" : "border-gray-400",
                ].join(" ")}
              >
                {isSelected && (
                  <span className="h-2 w-2 rounded-full bg-primary" />
                )}
              </span>
              {t(opt.labelKey as Parameters<typeof t>[0])}
            </button>
          );
        })}
      </div>
    </FilterAccordionSection>
  );
};

export default ListingTypeFilter;
