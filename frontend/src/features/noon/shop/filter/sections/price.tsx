"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";

import { Label } from "@/src/components/ui/label";
import { Button } from "@/src/components/ui/button";
import { Input } from "@/src/components/ui/base-inputs/input";
import useShopFilterParams from "@/src/features/noon/shop/filter/helpers/use-shop-filter-params";
import { PriceRange } from "@/src/features/noon/shop/types";

import FilterAccordionSection from "./filter-accordion-section";

const MIN_PRICE_PARAM = "min_price";
const MAX_PRICE_PARAM = "max_price";

type PriceFilterProps = {
  priceRange?: PriceRange | null;
};

const PriceFilter = ({ priceRange }: PriceFilterProps) => {
  const t = useTranslations();
  const searchParams = useSearchParams();
  const { setFilters } = useShopFilterParams();

  const [from, setFrom] = useState(searchParams.get(MIN_PRICE_PARAM) ?? "");
  const [to, setTo] = useState(searchParams.get(MAX_PRICE_PARAM) ?? "");

  const handleApply = () => {
    setFilters([
      [MIN_PRICE_PARAM, from],
      [MAX_PRICE_PARAM, to],
    ]);
  };

  return (
    <FilterAccordionSection value="price" title="Price">
      <div className="flex items-end gap-2">
        <div className="flex flex-1 flex-col gap-1">
          <Label htmlFor="price-from" className="text-xs text-muted-foreground">
            From
          </Label>
          <Input
            id="price-from"
            type="number"
            min={priceRange?.min ?? 0}
            max={priceRange?.max}
            value={from}
            onChange={(e) => setFrom(e.target.value)}
          />
        </div>
        <div className="flex flex-1 flex-col gap-1">
          <Label htmlFor="price-to" className="text-xs text-muted-foreground">
            To
          </Label>
          <Input
            id="price-to"
            type="number"
            min={priceRange?.min ?? 0}
            max={priceRange?.max}
            value={to}
            onChange={(e) => setTo(e.target.value)}
          />
        </div>
        <Button
          type="button"
          size="sm"
          className="h-8 px-1 text-xs text-blue-600"
          onClick={handleApply}
        >
          {t("go")}
        </Button>
      </div>
    </FilterAccordionSection>
  );
};

export default PriceFilter;
