"use client";

import { useState, useCallback } from "react";
import { LayoutList, Map as MapIcon } from "lucide-react";
import ClassifiedCard from "./classified-card";
import ClassifiedSidebar from "./classified-sidebar";
import ActiveFiltersBar from "./active-filters-bar";
import ClassifiedMapView from "./classified-map-view";
import { getClassifiedMapPinsService, MapPin } from "./api/get";
import { IClassifiedsList, IClassifiedCategoriesList } from "./helpers/types";
import Image from "next/image";
import { useTranslations } from "next-intl";

interface ClassifiedsPageClientProps {
  initialPins: MapPin[];
  data: IClassifiedsList | null;
  classifiedCategories: IClassifiedCategoriesList[];
  categoryId?: string;
  selectedCategory: IClassifiedCategoriesList | null;
}

export default function ClassifiedsPageClient({
  initialPins,
  data,
  classifiedCategories,
  categoryId,
  selectedCategory,
}: ClassifiedsPageClientProps) {
  const t = useTranslations("classified");
  const [viewMode, setViewMode] = useState<"list" | "map">("list");
  const [hoveredId, setHoveredId] = useState<string | null>(null);
  const [pins, setPins] = useState<MapPin[]>(initialPins);

  const handleBoundsChange = useCallback(
    async (bounds: {
      south: number;
      north: number;
      east: number;
      west: number;
    }) => {
      try {
        const res = await getClassifiedMapPinsService({
          bounds,
          category: categoryId,
        });
        if (res?.data) setPins(res.data);
      } catch {
        // silently ignore bounds-change errors
      }
    },
    [categoryId],
  );

  const listings = data?.listings?.items ?? [];
  const total = data?.listings?.meta?.total ?? 0;

  return (
    <>
      {/* View toggle */}
      <div className="flex items-center justify-end mb-3 gap-2">
        <button
          type="button"
          onClick={() => setViewMode("list")}
          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors ${
            viewMode === "list"
              ? "bg-blue-600 text-white border-blue-600"
              : "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
          }`}
        >
          <LayoutList className="w-4 h-4" />
          <span>{t("listView")}</span>
        </button>
        <button
          type="button"
          onClick={() => setViewMode("map")}
          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors ${
            viewMode === "map"
              ? "bg-blue-600 text-white border-blue-600"
              : "bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
          }`}
        >
          <MapIcon className="w-4 h-4" />
          <span>{t("mapView")}</span>
        </button>
      </div>

      {viewMode === "list" ? (
        /* List layout */
        <div className="flex flex-col lg:flex-row gap-6 items-start">
          <ClassifiedSidebar
            categories={classifiedCategories}
            selectedCtg={categoryId || null}
          />
          <main className="flex-1 w-full min-w-0">
            <ActiveFiltersBar selectedCtg={selectedCategory || null} />
            {total === 0 && (
              <Image
                src="/images/no_products.jpg"
                alt={t("noProductsFound")}
                width={400}
                height={400}
                className="mx-auto mt-10"
              />
            )}
            {listings.map((listing) => (
              <div
                key={listing.listing_id}
                onMouseEnter={() => setHoveredId(listing.listing_id)}
                onMouseLeave={() => setHoveredId(null)}
              >
                <ClassifiedCard listing={listing} />
              </div>
            ))}
          </main>
        </div>
      ) : (
        /* Map split layout */
        <div className="flex gap-0 items-stretch h-[calc(100vh-220px)] min-h-[500px]">
          {/* Left scrollable list */}
          <div className="w-[380px] shrink-0 overflow-y-auto border-r border-gray-200 bg-white">
            <ActiveFiltersBar selectedCtg={selectedCategory || null} />
            {listings.map((listing) => (
              <div
                key={listing.listing_id}
                onMouseEnter={() => setHoveredId(listing.listing_id)}
                onMouseLeave={() => setHoveredId(null)}
              >
                <ClassifiedCard listing={listing} />
              </div>
            ))}
          </div>

          {/* Right map */}
          <div className="flex-1 relative">
            <ClassifiedMapView
              pins={pins}
              onBoundsChange={handleBoundsChange}
              hoveredId={hoveredId}
            />
          </div>
        </div>
      )}
    </>
  );
}
