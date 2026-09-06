import React from "react";
import ClassifiedGallery from "./classified-gallery";
import ClassifiedHeaderDetails from "./classified-header-details";
import ClassifiedSpecsTable from "./classified-specs-table";
import ClassifiedDescription from "./classified-description";
import ClassifiedFeatures from "./classified-features";
import ClassifiedInquiry from "./classified-inquiry";
import ClassifiedSidebar from "./classified-sidebar";
import ClassifiedRecommended from "./classified-recommended";
import {
  getClassifiedDetailsService,
  getRelatedClassifiedService,
} from "./api/get";
import getLocale from "@/src/helpers/getLocale";
import { MOCK_CLASSIFIED_DETAIL } from "./mock-data";

interface ClassifiedViewProps {
  slug: string;
}

export default async function ClassifiedView({ slug }: ClassifiedViewProps) {
  const { data: listing } = await getClassifiedDetailsService(slug);
  const { data: relatedListings } = await getRelatedClassifiedService(slug);
  const locale = await getLocale();
  return (
    // <main className="min-h-screen bg-gray-50/50 py-16">
    <div className="container py-8">
      {/* Main 2-Column Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-7 items-start">
        {/* Main Left Content Area (8 cols on lg) */}
        <div className="lg:col-span-8 bg-white border border-gray-200/80 rounded-2xl p-4 sm:p-6 shadow-xs">
          {/* 1. Media Gallery */}
          <ClassifiedGallery
            images={listing.images}
            promotedBadge={"dummy data"}
          />

          {/* 2. Header & Pricing Details */}
          <ClassifiedHeaderDetails listing={listing} />

          {/* 3. Specifications Table */}
          <ClassifiedSpecsTable
            column1={[
              { label: "Body Type", value: "SUV" },
              { label: "Number of Seats", value: "5" },
              { label: "Transmission", value: "Automatic" },
              { label: "Engine Size (cc)", value: "1,000 - 1,599 cc" },
              { label: "Exterior Color", value: "Nardo Grey" },
              { label: "Interior Color", value: "Nardo Grey" },
              { label: "Payment Method", value: "Cash" },
              { label: "Car Make", value: "Kia" },
            ]}
            column2={[
              {
                label: "Location on Map",
                value: "Ask for Exact Location",
                isLink: true,
                href: "#map",
              },
              { label: "Neighborhood", value: "Other" },
              { label: "City", value: "Minya" },
              { label: "Sub Category", value: "Cars for Sale" },
              { label: "Category", value: "Autos" },
              { label: "Listing Id", value: "266147908" },
              { label: "Published Date", value: "04-09-2026" },
            ]}
          />

          {/* 4. Description */}
          <ClassifiedDescription description={listing.description[locale]} />

          {/* 5. Features Checklist */}
          <ClassifiedFeatures
            features={[
              {
                name: "Interior",
                count: 15,
                items: [
                  "Center Lock",
                  "Air Condition",
                  "Heated Seats",
                  "Alarm System",
                  "CD player",
                ],
              },
              {
                name: "Exterior",
                count: 11,
                items: [
                  "Electric Mirrors",
                  "Xenon Lights",
                  "Daytime Running Lights",
                  "LED Lights",
                  "Spare Tyre",
                ],
              },
              {
                name: "Technology",
                count: 18,
                items: [
                  "Blind Spot Alert",
                  "Traction Control",
                  "Tyre Pressure Monitoring",
                  "Cruise Control",
                  "Touch Screen",
                ],
              },
            ]}
          />

          {/* 6. Ask the Lister */}
          <ClassifiedInquiry sellerName={listing.seller.display_name} />
        </div>

        {/* Sticky Right Sidebar (4 cols on lg) */}
        <div className="lg:col-span-4 lg:sticky lg:top-28">
          <ClassifiedSidebar
            seller={listing.seller}
            // onOpenChat={() => {
            //   const el = document.querySelector(
            //     "input[placeholder='Message...']",
            //   );
            //   if (el) {
            //     (el as HTMLInputElement).focus();
            //     el.scrollIntoView({ behavior: "smooth", block: "center" });
            //   }
            // }}
          />
        </div>
      </div>

      {/* Bottom Full-Width Section: Recommended Listings */}
      {relatedListings?.items.length > 0 && (
        <ClassifiedRecommended items={relatedListings?.items} />
      )}
    </div>
    // </main>
  );
}
