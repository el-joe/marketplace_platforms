import { Label } from "@/src/components/ui/label";

import FilterAccordionSection from "./filter-accordion-section";
import { colourOptions } from "../filter-data";

const ColourFilter = () => (
  <FilterAccordionSection value="colour" title="Colour">
    <div className="grid max-h-[250px] grid-cols-2 gap-2 overflow-y-auto">
      {colourOptions.map((colour) => (
        <Label
          key={colour.name}
          className="flex items-center gap-2 rounded-lg border border-input p-2 font-normal cursor-pointer"
        >
          <span
            className="size-5 shrink-0 rounded-sm border border-input"
            style={{ backgroundColor: colour.value }}
          />
          <span className="truncate text-xs">{colour.name}</span>
        </Label>
      ))}
    </div>
  </FilterAccordionSection>
);

export default ColourFilter;
