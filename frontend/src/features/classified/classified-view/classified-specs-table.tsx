"use client";

import React, { useState } from "react";
import { ChevronDown, ChevronUp } from "lucide-react";
import { ClassifiedSpecItem } from "./types";

interface ClassifiedSpecsTableProps {
  column1: ClassifiedSpecItem[];
  column2: ClassifiedSpecItem[];
}

export default function ClassifiedSpecsTable({
  column1,
  column2,
}: ClassifiedSpecsTableProps) {
  const [isExpanded, setIsExpanded] = useState(false);

  // When collapsed show up to 6 items per column; when expanded show all
  const displayedCol1 = isExpanded ? column1 : column1.slice(0, 6);
  const displayedCol2 = isExpanded ? column2 : column2.slice(0, 6);

  const maxLength = Math.max(displayedCol1.length, displayedCol2.length);

  return (
    <div className="w-full mt-4 bg-white rounded-lg">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-0 text-xs sm:text-sm">
        {/* Column 1 */}
        <div className="flex flex-col divide-y divide-gray-100 border-t md:border-t-0">
          {displayedCol1.map((item, idx) => (
            <div
              key={idx}
              className={`flex items-center justify-between py-2.5 px-3 ${
                idx % 2 === 0 ? "bg-gray-50/70" : "bg-white"
              } rounded`}
            >
              <span className="text-gray-500 font-normal">{item.label}</span>
              <span className="text-gray-900 font-semibold text-end">
                {item.isLink ? (
                  <a
                    href={item.href || "#"}
                    className="text-blue-600 hover:underline"
                  >
                    {item.value}
                  </a>
                ) : (
                  item.value
                )}
              </span>
            </div>
          ))}
        </div>

        {/* Column 2 */}
        <div className="flex flex-col divide-y divide-gray-100 border-t md:border-t-0">
          {displayedCol2.map((item, idx) => (
            <div
              key={idx}
              className={`flex items-center justify-between py-2.5 px-3 ${
                idx % 2 === 0 ? "bg-gray-50/70" : "bg-white"
              } rounded`}
            >
              <span className="text-gray-500 font-normal">{item.label}</span>
              <span className="text-gray-900 font-semibold text-end">
                {item.isLink ? (
                  <a
                    href={item.href || "#"}
                    className="text-blue-600 hover:underline"
                  >
                    {item.value}
                  </a>
                ) : (
                  item.value
                )}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* View More / View Less Toggle Button */}
      {(column1.length > 6 || column2.length > 6) && (
        <button
          onClick={() => setIsExpanded(!isExpanded)}
          className="mt-2.5 text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors"
        >
          <span>{isExpanded ? "View Less" : "View More"}</span>
          {isExpanded ? (
            <ChevronUp className="w-4 h-4" />
          ) : (
            <ChevronDown className="w-4 h-4" />
          )}
        </button>
      )}
    </div>
  );
}
