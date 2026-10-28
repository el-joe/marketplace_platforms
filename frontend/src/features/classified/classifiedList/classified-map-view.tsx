"use client";

import { useCallback, useEffect, useRef } from "react";
import {
  APIProvider,
  Map,
  AdvancedMarker,
  MapCameraChangedEvent,
  useMap,
} from "@vis.gl/react-google-maps";
import useLocale from "@/src/hooks/use-locale";
import { MapPin } from "./api/get";

interface ClassifiedMapViewProps {
  pins: MapPin[];
  /** Pins the map zooms to fit whenever this array changes (the items matching the current filters). */
  fitPins: MapPin[];
  onBoundsChange: (bounds: {
    south: number;
    north: number;
    east: number;
    west: number;
  }) => void;
  /** Pin highlighted because its card is hovered. */
  hoveredId?: string | null;
  /** Pin the user clicked; the map pans to it. */
  activeId?: string | null;
  onPinSelect?: (id: string) => void;
}

const MAX_FIT_ZOOM = 16;

/** Zooms to fit every matching item, then pans to the pin the user picks. */
function MapController({
  pins,
  fitPins,
  activeId,
}: {
  pins: MapPin[];
  fitPins: MapPin[];
  activeId?: string | null;
}) {
  const map = useMap();

  useEffect(() => {
    if (!map || !fitPins.length) return;
    const bounds = new google.maps.LatLngBounds();
    fitPins.forEach((p) => bounds.extend({ lat: p.lat, lng: p.lng }));
    map.fitBounds(bounds, 48);
    // One item (or items on the same spot) would otherwise zoom in to street level.
    google.maps.event.addListenerOnce(map, "idle", () => {
      if ((map.getZoom() ?? 0) > MAX_FIT_ZOOM) map.setZoom(MAX_FIT_ZOOM);
    });
  }, [map, fitPins]);

  useEffect(() => {
    const pin = pins.find((p) => p.id === activeId);
    if (map && pin) map.panTo({ lat: pin.lat, lng: pin.lng });
    // Only react to a new selection, not to every pin refresh.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, activeId]);

  return null;
}

export default function ClassifiedMapView({
  pins,
  fitPins,
  onBoundsChange,
  hoveredId,
  activeId,
  onPinSelect,
}: ClassifiedMapViewProps) {
  const locale = useLocale();
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Pin prices are already whole amounts from the API (no minor-unit scaling).
  const formatPrice = (price: number, currency: string) =>
    `${new Intl.NumberFormat(locale === "ar" ? "ar-AE" : "en-US", {
      notation: "compact",
      maximumFractionDigits: 1,
    }).format(price)} ${currency}`;

  const handleBoundsChanged = useCallback(
    (e: MapCameraChangedEvent) => {
      const bounds = e.detail.bounds;
      if (!bounds) return;
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        onBoundsChange({
          south: bounds.south,
          north: bounds.north,
          east: bounds.east,
          west: bounds.west,
        });
      }, 500);
    },
    [onBoundsChange],
  );

  return (
    <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY!}>
      <Map
        defaultCenter={{ lat: 24.7136, lng: 46.6753 }}
        defaultZoom={11}
        mapId="classified-map"
        gestureHandling="greedy"
        disableDefaultUI
        zoomControl
        onBoundsChanged={handleBoundsChanged}
        className="h-full w-full"
      >
        <MapController pins={pins} fitPins={fitPins} activeId={activeId} />
        {pins.map((pin) => {
          const highlighted = hoveredId === pin.id || activeId === pin.id;
          return (
            <AdvancedMarker
              key={pin.id}
              position={{ lat: pin.lat, lng: pin.lng }}
              zIndex={highlighted ? 10 : undefined}
              onClick={() => onPinSelect?.(pin.id)}
            >
              <div
                className={`cursor-pointer whitespace-nowrap rounded-full border px-2 py-1 text-xs font-semibold shadow-md transition-all ${
                  highlighted
                    ? "scale-110 border-gray-900 bg-gray-900 text-white"
                    : "border-gray-200 bg-white text-gray-900 hover:bg-gray-900 hover:text-white"
                }`}
              >
                {formatPrice(pin.price, pin.currency)}
              </div>
            </AdvancedMarker>
          );
        })}
      </Map>
    </APIProvider>
  );
}
