"use client";

import React, { useState } from "react";
import { Flag, ChevronDown, ChevronUp } from "lucide-react";

interface ClassifiedDescriptionProps {
  description: string;
}

export default function ClassifiedDescription({
  description,
}: ClassifiedDescriptionProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isReported, setIsReported] = useState(false);

  const handleReport = () => {
    const reason = window.prompt(
      "Please state the reason for reporting this listing (e.g. Fraud, Incorrect Information, Duplicate):",
    );
    if (reason) {
      setIsReported(true);
      alert("Thank you. The listing has been reported for review.");
    }
  };

  return (
    <div className="w-full mt-6 pt-5 border-t border-gray-100">
      <h2 className="text-base sm:text-lg font-bold text-gray-900 mb-3">
        Description
      </h2>

      {/* Description Content */}
      <div
        className={`text-xs sm:text-sm text-gray-800 whitespace-pre-line leading-relaxed transition-all duration-300 ${
          isExpanded ? "" : "line-clamp-4"
        }`}
        dir="rtl"
      >
        {description}
      </div>

      {/* Footer: View More and Report Listing */}
      <div className="flex items-center justify-between mt-3">
        <button
          onClick={() => setIsExpanded(!isExpanded)}
          className="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors"
        >
          <span>{isExpanded ? "View Less" : "View More"}</span>
          {isExpanded ? (
            <ChevronUp className="w-4 h-4" />
          ) : (
            <ChevronDown className="w-4 h-4" />
          )}
        </button>

        <button
          onClick={handleReport}
          className="text-xs sm:text-sm font-medium text-red-600 hover:text-red-700 hover:underline flex items-center gap-1.5 transition-colors"
        >
          <Flag className="w-3.5 h-3.5" />
          <span>{isReported ? "Listing Reported" : "Report Listing"}</span>
        </button>
      </div>
    </div>
  );
}
