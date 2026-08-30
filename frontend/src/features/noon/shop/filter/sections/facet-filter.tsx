"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";

import { Label } from "@/src/components/ui/label";
import { Input } from "@/src/components/ui/base-inputs/input";
import useLocale from "@/src/hooks/use-locale";
import useShopFilterParams from "@/src/features/noon/shop/filter/helpers/use-shop-filter-params";
import { FacetAttribute } from "@/src/features/noon/shop/types";

import FilterAccordionSection from "./filter-accordion-section";
import OptionsList from "./options-list";
import ColorOptionsList from "./color-options-list";

type FacetFilterProps = {
  attribute: FacetAttribute;
};

const FacetFilter = ({ attribute }: FacetFilterProps) => {
  const locale = useLocale();
  const searchParams = useSearchParams();
  const { setFilter } = useShopFilterParams();

  const paramKey = attribute.code;
  const title = attribute.name[locale];

  if (attribute.type === "color") {
    const selectedValues =
      searchParams.get(paramKey)?.split(",").filter(Boolean) ?? [];

    const handleToggle = (value: string, checked: boolean) => {
      const nextValues = checked
        ? [...selectedValues, value]
        : selectedValues.filter((v) => v !== value);

      setFilter(attribute.code, nextValues.join(","));
    };

    return (
      <FilterAccordionSection value={attribute.code} title={title}>
        <ColorOptionsList
          values={attribute.values}
          selectedValues={selectedValues}
          onToggle={handleToggle}
        />
      </FilterAccordionSection>
    );
  }

  if (attribute.type === "select") {
    const optionsList = attribute.values.map((value) => value.value[locale]);
    const selectedValues =
      searchParams.get(paramKey)?.split(",").filter(Boolean) ?? [];

    const handleToggle = (value: string, checked: boolean) => {
      const nextValues = checked
        ? [...selectedValues, value]
        : selectedValues.filter((v) => v !== value);

      setFilter(attribute.code, nextValues.join(","));
    };

    return (
      <FilterAccordionSection value={attribute.code} title={title}>
        <OptionsList
          options={optionsList}
          selectedValues={selectedValues}
          onToggle={handleToggle}
        />
      </FilterAccordionSection>
    );
  }

  return (
    <FilterAccordionSection value={attribute.code} title={title}>
      <FacetInput
        id={`facet-${attribute.code}`}
        type={attribute.type}
        defaultValue={searchParams.get(paramKey) ?? ""}
        onCommit={(value) => setFilter(attribute.code, value)}
      />
    </FilterAccordionSection>
  );
};

type FacetInputProps = {
  id: string;
  type: string;
  label?: string;
  defaultValue: string;
  onCommit: (value: string) => void;
};

const FacetInput = ({
  id,
  type,
  label,
  defaultValue,
  onCommit,
}: FacetInputProps) => {
  const [value, setValue] = useState(defaultValue);

  return (
    <div className="flex flex-1 flex-col gap-1">
      <Label htmlFor={id} className="text-xs text-muted-foreground">
        {label}
      </Label>
      <Input
        id={id}
        type={type}
        value={value}
        onChange={(e) => {
          setValue(e.target.value);
          onCommit(e.target.value);
        }}
      />
    </div>
  );
};

export default FacetFilter;
