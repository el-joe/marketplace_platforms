"use client";

import { cn } from "@/src/lib/utils";
import useLocale from "@/src/hooks/use-locale";
import { FacetValue } from "@/src/features/noon/shop/types";

type ColorOptionsListProps = {
  values: FacetValue[];
  selectedValues?: string[];
  onToggle?: (value: string, checked: boolean) => void;
};

const ColorOptionsList = ({
  values,
  selectedValues,
  onToggle,
}: ColorOptionsListProps) => {
  const locale = useLocale();

  return (
    <div className="flex flex-wrap gap-2">
      {values.map((facetValue) => {
        const label = facetValue.value[locale];
        const isSelected = selectedValues?.includes(label);

        return (
          <button
            key={facetValue.id}
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={isSelected}
            onClick={() => onToggle?.(label, !isSelected)}
            className={cn(
              "size-[25px]! rounded border border-gray-300 cursor-pointer",
              isSelected && "ring-2 ring-offset-1 ring-primary",
            )}
            style={{ backgroundColor: facetValue.value.en ?? undefined }}
          />
        );
      })}
    </div>
  );
};

export default ColorOptionsList;
