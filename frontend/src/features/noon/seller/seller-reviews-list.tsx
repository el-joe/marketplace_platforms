"use client";

import React, { useState } from "react";
import {
  CheckCircle2,
  ChevronRight,
  Flag,
  Languages,
  Star,
} from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { ISellerReview } from "./types";

interface SellerReviewsListProps {
  totalRatingsCount: number;
  totalReviewsCount: number;
  reviews: ISellerReview[];
}

export default function SellerReviewsList({
  totalRatingsCount,
  totalReviewsCount,
  reviews,
}: SellerReviewsListProps) {
  const [translatedMap, setTranslatedMap] = useState<Record<string, boolean>>(
    {},
  );
  const [allTranslated, setAllTranslated] = useState(false);

  const toggleTranslate = (reviewId: string) => {
    setTranslatedMap((prev) => ({
      ...prev,
      [reviewId]: !prev[reviewId],
    }));
  };

  const toggleTranslateAll = () => {
    const nextState = !allTranslated;
    setAllTranslated(nextState);
    const updated: Record<string, boolean> = {};
    reviews.forEach((r) => {
      if (r.translated_comment) {
        updated[r.id] = nextState;
      }
    });
    setTranslatedMap(updated);
  };

  return (
    <div className="pt-2 pb-12">
      {/* Subheader: Ratings & Reviews summary text + Translate button */}
      <div className="border-t border-gray-200 pt-4 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p className="text-xs text-gray-500 font-normal">
          There are {totalRatingsCount.toLocaleString()} ratings and{" "}
          {totalReviewsCount.toLocaleString()} reviews for this seller
        </p>

        <Button
          onClick={toggleTranslateAll}
          variant="outline"
          size="sm"
          className="bg-[#edf2fd] hover:bg-[#e2ebfc] text-[#3866df] border-transparent text-xs font-medium px-3 py-1.5 h-auto rounded-md flex items-center gap-1.5 w-fit"
        >
          <Languages className="w-3.5 h-3.5" />
          <span>
            {allTranslated ? "Show original reviews" : "Translate all reviews"}
          </span>
        </Button>
      </div>

      {/* Reviews List */}
      <div className="divide-y divide-gray-100">
        {reviews.map((review) => {
          const isTranslated = !!translatedMap[review.id];
          const displayComment =
            isTranslated && review.translated_comment
              ? review.translated_comment
              : review.comment;

          return (
            <div key={review.id} className="py-5 space-y-2.5">
              {/* Reviewer Header: Avatar, Name, Verified Badge, Date */}
              <div className="flex items-start gap-3">
                <div className="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-semibold shrink-0">
                  {review.avatar_letter || review.reviewer_name.charAt(0)}
                </div>

                <div className="flex flex-col">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-gray-900">
                      {review.reviewer_name}
                    </span>
                    {review.is_verified_purchase && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-700">
                        <CheckCircle2 className="w-3 h-3 text-gray-800" />
                        Verified Purchase
                      </span>
                    )}
                  </div>
                  <span className="text-[11px] text-gray-400 mt-0.5">
                    {review.date}
                  </span>
                </div>
              </div>

              {/* Star Rating */}
              <div className="flex items-center gap-0.5">
                {Array.from({ length: 5 }).map((_, i) => {
                  const filled = i < review.rating;
                  return (
                    <Star
                      key={i}
                      className={`w-3.5 h-3.5 ${
                        filled
                          ? "fill-[#006300] stroke-[#006300]"
                          : "fill-gray-200 stroke-gray-200"
                      }`}
                    />
                  );
                })}
              </div>

              {/* Review Content */}
              <p className="text-xs sm:text-sm text-gray-800 font-normal leading-relaxed">
                {displayComment}
              </p>

              {/* Translation Action if non-English */}
              {review.translated_comment && (
                <div>
                  <button
                    type="button"
                    onClick={() => toggleTranslate(review.id)}
                    className="text-xs text-[#3866df] hover:underline font-medium"
                  >
                    {isTranslated ? "Show original" : "Translate to English"}
                  </button>
                </div>
              )}

              {/* Report Action */}
              <div className="pt-1">
                <button
                  type="button"
                  className="inline-flex items-center gap-1 text-[11px] text-gray-400 hover:text-gray-600 transition-colors"
                >
                  <Flag className="w-3 h-3" />
                  <span>Report</span>
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* View All Reviews Button */}
      <div className="mt-8">
        <Button
          variant="outline"
          className="border-[#3866df] text-[#3866df] hover:bg-[#edf2fd] rounded-lg px-6 py-2.5 text-xs sm:text-sm font-semibold flex items-center gap-1 transition-colors"
        >
          <span>View All Reviews</span>
          <ChevronRight className="w-4 h-4 rtl:rotate-180" />
        </Button>
      </div>
    </div>
  );
}
