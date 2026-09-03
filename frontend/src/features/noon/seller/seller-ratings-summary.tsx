"use client";

import React from "react";
import { Star } from "lucide-react";
import { IRatingBreakdown } from "./types";

interface SellerRatingsSummaryProps {
  ratingAvg: number;
  totalRatingsCount: number;
  breakdown: IRatingBreakdown[];
}

const STAR_COLORS: Record<number, string> = {
  5: "#006300",
  4: "#006300",
  3: "#05af25",
  2: "#f8b200",
  1: "#f36302",
};

export default function SellerRatingsSummary({
  ratingAvg,
  totalRatingsCount,
  breakdown,
}: SellerRatingsSummaryProps) {
  return (
    <div className="pt-6 pb-6">
      {/* Section Title */}
      <h2 className="text-lg sm:text-xl font-bold text-gray-900 mb-6">
        Seller Ratings & Reviews
      </h2>

      {/* Ratings Overview & Breakdown Grid */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-8 sm:gap-14">
        {/* Left: Overall Rating */}
        <div className="flex flex-col">
          <div className="text-4xl sm:text-5xl font-bold text-gray-900 tracking-tight leading-none mb-2">
            {ratingAvg.toFixed(1)}
          </div>

          {/* 5 Green Stars */}
          <div className="flex items-center gap-1 my-1">
            {Array.from({ length: 5 }).map((_, i) => (
              <Star
                key={i}
                className="w-4 h-4 sm:w-5 sm:h-5 fill-[#006300] stroke-[#006300]"
              />
            ))}
          </div>

          <p className="text-xs text-gray-500 mt-1">
            Based on {totalRatingsCount.toLocaleString()} ratings
          </p>
        </div>

        {/* Right: Star Breakdown Progress Bars */}
        <div className="flex-1 max-w-md flex flex-col gap-1.5">
          {breakdown.map((item) => {
            const color = STAR_COLORS[item.stars] || "#006300";
            return (
              <div key={item.stars} className="flex items-center gap-2 text-xs">
                {/* Star level */}
                <div className="flex items-center gap-1 w-6 text-gray-700 font-medium">
                  <span>{item.stars}</span>
                  <Star
                    className="w-3 h-3 shrink-0"
                    style={{ fill: color, stroke: color }}
                  />
                </div>

                {/* Progress bar track & fill */}
                <div className="h-1.5 bg-gray-200 flex-1 rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full transition-all duration-500"
                    style={{
                      width: `${item.percentage}%`,
                      backgroundColor: color,
                    }}
                  />
                </div>

                {/* Percentage */}
                <div className="w-8 text-right font-medium text-gray-600">
                  {item.percentage}%
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
