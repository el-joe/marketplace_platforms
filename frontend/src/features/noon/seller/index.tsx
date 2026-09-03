"use client";

import React from "react";
import SellerBanner from "./seller-banner";
import SellerInfoSidebar from "./seller-info-sidebar";
import SellerRatingsSummary from "./seller-ratings-summary";
import SellerReviewsList from "./seller-reviews-list";
import { MOCK_SELLER_DATA } from "./mock-data";
import { ISellerProfile } from "./types";

interface SellerViewProps {
  sellerId?: string;
  initialData?: ISellerProfile;
}

export default function SellerView({
  sellerId,
  initialData = MOCK_SELLER_DATA,
}: SellerViewProps) {
  // Use initial data or mock data (can be updated when API is connected)
  const seller = initialData;

  return (
    <div className="bg-white min-h-screen">
      {/* Top Banner with Store Logo and Share Button */}
      <SellerBanner storeName={seller.store_name} />

      {/* Main Content Layout */}
      <div className="container">
        <div className="flex flex-col lg:flex-row gap-0 lg:gap-8 items-start">
          {/* Left Column: Seller Information Sidebar */}
          <SellerInfoSidebar seller={seller} />

          {/* Desktop Vertical Divider */}
          <div className="hidden lg:block w-px bg-gray-200 self-stretch my-6" />

          {/* Right Column: Ratings Breakdown and Reviews List */}
          <main className="flex-1 w-full lg:pt-14 pb-12">
            <SellerRatingsSummary
              ratingAvg={seller.seller_rating}
              totalRatingsCount={seller.total_ratings_count}
              breakdown={seller.rating_breakdown}
            />

            <SellerReviewsList
              totalRatingsCount={seller.total_ratings_count}
              totalReviewsCount={seller.total_reviews_count}
              reviews={seller.reviews}
            />
          </main>
        </div>
      </div>
    </div>
  );
}
