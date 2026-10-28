"use client";

import { useState, useCallback, useEffect, useRef } from "react";
import { LayoutList, Map as MapIcon } from "lucide-react";
import ClassifiedCard from "./classified-card";
import ClassifiedSidebar from "./classified-sidebar";
import ActiveFiltersBar from "./active-filters-bar";
import ClassifiedMapView from "./classified-map-view";
import ClassifiedMapCard from "./classified-map-card";
import {
  ClassifiedLocationFilters,
  getClassifiedMapPinsService,
  MapPin,
} from "./api/get";
import { IClassifiedsList, IClassifiedCategoriesList } from "./helpers/types";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Loader2Icon } from "lucide-react";

interface ClassifiedsPageClientProps {
  initialPins: MapPin[];
  /** Active city / near-me filters, applied to the pins as well as the list. */
  location: ClassifiedLocationFilters;
  data: IClassifiedsList | null;
  classifiedCategories: IClassifiedCategoriesList[];
  categoryId?: string;
  selectedCategory: IClassifiedCategoriesList | null;
}

export default function ClassifiedsPageClient({
  initialPins,
  location,
  data,
  classifiedCategories,
  categoryId,
  selectedCategory,
}: ClassifiedsPageClientProps) {
  const t = useTranslations("classified");
  const [viewMode, setViewMode] = useState<"list" | "map">("list");
  const [hoveredId, setHoveredId] = useState<string | null>(null);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [pins, setPins] = useState<MapPin[]>(initialPins);
  const [isUpdatingPins, setIsUpdatingPins] = useState(false);
  const latestRequest = useRef(0);

  // New filters re-render the page server-side with a new set of pins; reset to them.
  const [syncedPins, setSyncedPins] = useState(initialPins);
  if (syncedPins !== initialPins) {
    setSyncedPins(initialPins);
    setPins(initialPins);
    setActiveId(null);
  }

  // Opening the map starts from every item that matches the current filters,
  // and the map zooms to fit them (see ClassifiedMapView `fitPins`).
  const openMap = () => {
    setPins(initialPins);
    setActiveId(null);
    setViewMode("map");
  };

  const handleBoundsChange = useCallback(
    async (bounds: {
      south: number;
      north: number;
      east: number;
      west: number;
    }) => {
      // Panning/fitting fires several requests; only the newest one may update the pins.
      const requestId = ++latestRequest.current;
      setIsUpdatingPins(true);
      try {
        const res = await getClassifiedMapPinsService({
          bounds,
          category: categoryId,
          location: {
            city_id: location.city_id,
            lat: location.lat,
            lng: location.lng,
            radius_km: location.radius_km,
          },
        });
        if (res?.data && requestId === latestRequest.current) setPins(res.data);
      } catch {
        // silently ignore bounds-change errors
      } finally {
        if (requestId === latestRequest.current) setIsUpdatingPins(false);
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [
      categoryId,
      location.city_id,
      location.lat,
      location.lng,
      location.radius_km,
    ],
  );

  // A picked pin brings its card into view (list on desktop, carousel on mobile).
  useEffect(() => {
    if (!activeId) return;
    document.getElementById(`pin-card-${activeId}`)?.scrollIntoView({
      behavior: "smooth",
      block: "nearest",
      inline: "center",
    });
  }, [activeId]);

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
          onClick={openMap}
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
        /* Map layout: the list shows the same pins the map draws for the visible area.
           Desktop = list column beside the map; mobile = map with a swipeable card carousel. */
        <div className="relative h-[calc(100dvh-300px)] min-h-[480px] overflow-hidden rounded-xl border border-gray-200 bg-white lg:flex lg:h-[calc(100vh-260px)] lg:min-h-[560px]">
          <aside className="absolute inset-x-0 bottom-3 z-10 lg:static lg:flex lg:w-[420px] lg:shrink-0 lg:flex-col lg:border-e lg:border-gray-200 xl:w-[460px]">
            <div className="hidden items-center justify-between border-b border-gray-200 px-4 py-3 text-sm lg:flex">
              <span className="font-semibold">
                {t("adsInArea", { count: pins.length })}
              </span>
              {isUpdatingPins && (
                <span className="flex items-center gap-1 text-xs text-gray-500">
                  <Loader2Icon className="size-3.5 animate-spin" />
                  {t("updatingArea")}
                </span>
              )}
            </div>
            <div className="flex snap-x snap-mandatory gap-2 overflow-x-auto px-3 pb-1 scrollbar-hide lg:flex-1 lg:snap-none lg:flex-col lg:gap-3 lg:overflow-y-auto lg:overflow-x-hidden lg:p-3">
              {pins.map((pin) => (
                <ClassifiedMapCard
                  key={pin.id}
                  pin={pin}
                  active={hoveredId === pin.id || activeId === pin.id}
                  onHover={setHoveredId}
                  className="w-[85%] shrink-0 snap-center shadow-md sm:w-[60%] lg:w-auto lg:shrink lg:shadow-none"
                />
              ))}
              {!pins.length && (
                <div className="mx-auto rounded-xl bg-white p-4 text-center shadow-md lg:py-10 lg:shadow-none">
                  <p className="font-semibold">{t("noAdsInArea")}</p>
                  <p className="text-sm text-gray-500">
                    {t("noAdsInAreaHint")}
                  </p>
                </div>
              )}
            </div>
          </aside>

          <div className="relative h-full flex-1">
            <ClassifiedMapView
              pins={pins}
              fitPins={initialPins}
              onBoundsChange={handleBoundsChange}
              hoveredId={hoveredId}
              activeId={activeId}
              onPinSelect={setActiveId}
            />
            {isUpdatingPins && (
              <div className="absolute start-3 top-3 flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs shadow lg:hidden">
                <Loader2Icon className="size-3.5 animate-spin" />
                {t("updatingArea")}
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
}
