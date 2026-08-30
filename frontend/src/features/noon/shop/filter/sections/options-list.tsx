"use client";

import { useState } from "react";

import { Label } from "@/src/components/ui/label";

import SeeAllToggle from "./see-all-toggle";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";

const DEFAULT_VISIBLE_COUNT = 5;

type OptionsListProps = {
  options: string[];
};

const OptionsList = ({ options }: OptionsListProps) => {
  const [expanded, setExpanded] = useState(false);
  const hasMore = options.length > DEFAULT_VISIBLE_COUNT;
  const visibleOptions = expanded
    ? options
    : options.slice(0, DEFAULT_VISIBLE_COUNT);

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-col gap-3">
        {visibleOptions.map((option) => (
          <Label key={option} className="font-normal cursor-pointer">
            <Checkbox />
            {option}
          </Label>
        ))}
      </div>
      {hasMore && (
        <SeeAllToggle
          expanded={expanded}
          totalCount={options.length}
          onToggle={() => setExpanded((prev) => !prev)}
        />
      )}
    </div>
  );
};

export default OptionsList;
