"use client";

import React, { useState } from "react";
import { Check, ChevronDown, ChevronUp } from "lucide-react";
import { FeatureGroup } from "./types";

interface ClassifiedFeaturesProps {
  features: FeatureGroup[];
}

export default function ClassifiedFeatures({
  features,
}: ClassifiedFeaturesProps) {
  const [isExpanded, setIsExpanded] = useState(false);

  return (
    <div className="w-full mt-6 pt-5 border-t border-gray-100">
      <h2 className="text-base sm:text-lg font-bold text-gray-900 mb-4">
        Features
      </h2>

      {/* Grid of feature columns */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        {features.map((group, gIdx) => {
          const displayedItems = isExpanded
            ? group.items
            : group.items.slice(0, 5);

          return (
            <div key={gIdx} className="flex flex-col">
              <h3 className="text-xs sm:text-sm font-bold text-gray-900 mb-2.5">
                {group.name} ({group.count})
              </h3>
              <ul className="space-y-2">
                {displayedItems.map((item, iIdx) => (
                  <li
                    key={iIdx}
                    className="flex items-center gap-2 text-xs sm:text-sm text-gray-700"
                  >
                    <Check className="w-4 h-4 text-emerald-600 shrink-0 stroke-[2.5]" />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </div>
          );
        })}
      </div>

      {/* View More button */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="mt-4 text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors"
      >
        <span>{isExpanded ? "View Less" : "View More"}</span>
        {isExpanded ? (
          <ChevronUp className="w-4 h-4" />
        ) : (
          <ChevronDown className="w-4 h-4" />
        )}
      </button>
    </div>
  );
}
