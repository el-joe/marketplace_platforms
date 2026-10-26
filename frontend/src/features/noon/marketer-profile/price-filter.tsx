"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";

import { Label } from "@/src/components/ui/label";
import { Button } from "@/src/components/ui/button";
import { Input } from "@/src/components/ui/base-inputs/input";
import { PriceRange } from "@/src/features/noon/shop/types";
import {
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";
import { DotIcon } from "lucide-react";
import useMarketerFilter from "./helpers/use-marketer-filter";

const MIN_PRICE_PARAM = "price_min";
const MAX_PRICE_PARAM = "price_max";

type PriceFilterProps = {
  priceRange?: PriceRange | null;
};

const PriceFilter = ({ priceRange }: PriceFilterProps) => {
  const t = useTranslations("marketerProfile");
  const searchParams = useSearchParams();
  const { setFilters } = useMarketerFilter();

  const [from, setFrom] = useState(searchParams.get(MIN_PRICE_PARAM) ?? "");
  const [to, setTo] = useState(searchParams.get(MAX_PRICE_PARAM) ?? "");

  const handleApply = () => {
    setFilters([
      [MIN_PRICE_PARAM, from],
      [MAX_PRICE_PARAM, to],
    ]);
  };

  const isActive =
    searchParams.get("price_min") || searchParams.get("price_max");

  return (
    <AccordionItem value={"price"} className="border-b-0! scrollbar-hide">
      <AccordionTrigger className={"font-bold flex items-center"}>
        {t("price")}
        {isActive && (
          <p className="text-green ms-1 flex items-center">
            {t("active")} <DotIcon />
          </p>
        )}
      </AccordionTrigger>
      <AccordionContent>
        <form
          className="flex items-center gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            handleApply();
          }}
        >
          <input
            type="number"
            min={0}
            inputMode="decimal"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            placeholder={`${t("priceMin")} ${Math.floor(priceRange?.min ?? 0)}`}
            className="rounded border border-border-color px-2 py-1.5 text-sm outline-none focus:border-main max-w-1/3"
          />
          <span className="text-gray">-</span>
          <input
            type="number"
            min={0}
            inputMode="decimal"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            placeholder={`${t("priceMax")} ${Math.ceil(priceRange?.max ?? 99999999)}`}
            className="rounded border border-border-color px-2 py-1.5 text-sm outline-none focus:border-main max-w-1/3"
          />
          <button
            type="submit"
            className="shrink-0 rounded bg-primary px-3 py-1.5 text-xs font-bold text-white"
          >
            {t("apply")}
          </button>
        </form>
      </AccordionContent>
    </AccordionItem>
  );
};

export default PriceFilter;
