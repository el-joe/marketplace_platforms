"use client";

import React from "react";
import ClassifiedGallery from "./classified-gallery";
import ClassifiedHeaderDetails from "./classified-header-details";
import ClassifiedSpecsTable from "./classified-specs-table";
import ClassifiedDescription from "./classified-description";
import ClassifiedFeatures from "./classified-features";
import ClassifiedInquiry from "./classified-inquiry";
import ClassifiedSidebar from "./classified-sidebar";
import ClassifiedRecommended from "./classified-recommended";
import { MOCK_CLASSIFIED_DETAIL } from "./mock-data";
import { ClassifiedDetail } from "./types";

interface ClassifiedViewProps {
  initialData?: ClassifiedDetail;
}

export default function ClassifiedView({
  initialData = MOCK_CLASSIFIED_DETAIL,
}: ClassifiedViewProps) {
  const listing = initialData;

  return (
    <main className="min-h-screen bg-gray-50/50 pb-16">
      <div className="max-w-[1240px] mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6">
        {/* Main 2-Column Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-7 items-start">
          {/* Main Left Content Area (8 cols on lg) */}
          <div className="lg:col-span-8 bg-white border border-gray-200/80 rounded-2xl p-4 sm:p-6 shadow-xs">
            {/* 1. Media Gallery */}
            <ClassifiedGallery
              images={listing.images}
              promotedBadge={listing.promotedBadge}
            />

            {/* 2. Header & Pricing Details */}
            <ClassifiedHeaderDetails listing={listing} />

            {/* 3. Specifications Table */}
            <ClassifiedSpecsTable
              column1={listing.specs.column1}
              column2={listing.specs.column2}
            />

            {/* 4. Description */}
            <ClassifiedDescription description={listing.description} />

            {/* 5. Features Checklist */}
            <ClassifiedFeatures features={listing.features} />

            {/* 6. Ask the Lister */}
            <ClassifiedInquiry sellerName={listing.seller.name} />
          </div>

          {/* Sticky Right Sidebar (4 cols on lg) */}
          <div className="lg:col-span-4 lg:sticky lg:top-20">
            <ClassifiedSidebar
              seller={listing.seller}
              onOpenChat={() => {
                const el = document.querySelector(
                  "input[placeholder='Message...']",
                );
                if (el) {
                  (el as HTMLInputElement).focus();
                  el.scrollIntoView({ behavior: "smooth", block: "center" });
                }
              }}
            />
          </div>
        </div>

        {/* Bottom Full-Width Section: Recommended Listings */}
        <ClassifiedRecommended items={listing.recommended} />
      </div>
    </main>
  );
}
