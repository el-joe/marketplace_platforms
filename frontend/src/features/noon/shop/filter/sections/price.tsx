"use client";

import { useState } from "react";

import { Label } from "@/src/components/ui/label";
import { Button } from "@/src/components/ui/button";

import FilterAccordionSection from "./filter-accordion-section";
import { useTranslations } from "next-intl";
import { Input } from "@/src/components/ui/base-inputs/input";

const PriceFilter = () => {
  const t = useTranslations();

  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

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
            min={0}
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
            min={0}
            value={to}
            onChange={(e) => setTo(e.target.value)}
          />
        </div>
        <Button
          type="button"
          size="sm"
          className="h-8 px-1 text-xs text-blue-600"
        >
          {t("go")}
        </Button>
      </div>
    </FilterAccordionSection>
  );
};

export default PriceFilter;
