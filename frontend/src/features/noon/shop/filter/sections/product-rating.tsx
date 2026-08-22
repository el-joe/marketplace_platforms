"use client";

import { useState } from "react";

import FilterAccordionSection from "./filter-accordion-section";

const ProductRatingFilter = () => {
  const [rating, setRating] = useState(0);

  return (
    <FilterAccordionSection value="rating" title="Product rating">
      <div className="flex flex-col gap-2 mt-1">
        <input
          type="range"
          min={0}
          max={5}
          step={0.1}
          value={rating}
          onChange={(e) => setRating(Number(e.target.value))}
          className="h-1.5 w-full cursor-pointer"
        />
        <span className="text-xs text-muted-foreground">
          {rating.toFixed(1)}★ & above
        </span>
      </div>
    </FilterAccordionSection>
  );
};

export default ProductRatingFilter;
