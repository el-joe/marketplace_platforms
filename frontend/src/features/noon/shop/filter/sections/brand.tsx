"use client";

import { useState } from "react";
import { SearchIcon } from "lucide-react";
import { Label } from "@/src/components/ui/label";

import FilterAccordionSection from "./filter-accordion-section";
import SeeAllToggle from "./see-all-toggle";
import { brandOptions } from "../filter-data";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";

const DEFAULT_VISIBLE_COUNT = 5;

const BrandFilter = () => {
  const [search, setSearch] = useState("");
  const [expanded, setExpanded] = useState(false);

  const filteredBrands = brandOptions.filter((brand) =>
    brand.toLowerCase().includes(search.trim().toLowerCase()),
  );
  const hasMore = filteredBrands.length > DEFAULT_VISIBLE_COUNT;
  const visibleBrands = expanded
    ? filteredBrands
    : filteredBrands.slice(0, DEFAULT_VISIBLE_COUNT);

  return (
    <FilterAccordionSection value="brand" title="Brand">
      <div className="flex flex-col gap-3">
        <Input
          placeholder="Search brand"
          value={search}
          startIcon={<SearchIcon className="w-5! h-5!" />}
          onChange={(e) => setSearch(e.target.value)}
        />
        <div className="flex max-h-[250px] flex-col gap-3 overflow-y-auto scrollbar-hide">
          {visibleBrands.map((brand) => (
            <Label key={brand} className="font-normal cursor-pointer">
              <Checkbox />
              {brand}
            </Label>
          ))}
        </div>
        {hasMore && (
          <SeeAllToggle
            expanded={expanded}
            totalCount={filteredBrands.length}
            onToggle={() => setExpanded((prev) => !prev)}
          />
        )}
      </div>
    </FilterAccordionSection>
  );
};

export default BrandFilter;
