"use client";

import { useCallback, useRef, useState } from "react";
import {
  APIProvider,
  Map,
  AdvancedMarker,
  MapCameraChangedEvent,
} from "@vis.gl/react-google-maps";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { X } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { MapPin } from "./api/get";

interface ClassifiedMapViewProps {
  pins: MapPin[];
  onBoundsChange: (bounds: {
    south: number;
    north: number;
    east: number;
    west: number;
  }) => void;
  hoveredId?: string | null;
  onPinClick?: (pin: MapPin) => void;
}

function formatPrice(price: number, currency: string) {
  return new Intl.NumberFormat("ar-SA", { notation: "compact" }).format(
    price / 100,
  ) + " " + currency;
}

export default function ClassifiedMapView({
  pins,
  onBoundsChange,
  hoveredId,
  onPinClick,
}: ClassifiedMapViewProps) {
  const t = useTranslations("classified");
  const [activePin, setActivePin] = useState<MapPin | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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
      }, 600);
    },
    [onBoundsChange],
  );

  const handlePinClick = (pin: MapPin) => {
    setActivePin(pin);
    onPinClick?.(pin);
  };

  return (
    <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY!}>
      <div className="relative h-full w-full">
        <Map
          defaultCenter={{ lat: 24.7136, lng: 46.6753 }}
          defaultZoom={11}
          mapId="classified-map"
          gestureHandling="greedy"
          onBoundsChanged={handleBoundsChanged}
          className="h-full w-full"
        >
          {pins.map((pin) => (
            <AdvancedMarker
              key={pin.id}
              position={{ lat: pin.lat, lng: pin.lng }}
              onClick={() => handlePinClick(pin)}
            >
              <div
                className={`px-2 py-1 rounded-full text-xs font-semibold shadow-md cursor-pointer whitespace-nowrap border transition-colors ${
                  hoveredId === pin.id || activePin?.id === pin.id
                    ? "bg-gray-900 text-white border-gray-900"
                    : "bg-white text-gray-900 border-gray-200 hover:bg-gray-900 hover:text-white"
                }`}
              >
                {formatPrice(pin.price, pin.currency)}
              </div>
            </AdvancedMarker>
          ))}
        </Map>

        {/* Popup card */}
        {activePin && (
          <div className="absolute bottom-6 left-1/2 -translate-x-1/2 bg-white rounded-xl shadow-xl w-72 overflow-hidden z-10">
            <button
              type="button"
              onClick={() => setActivePin(null)}
              className="absolute top-2 end-2 bg-white/80 hover:bg-white rounded-full p-0.5 shadow z-20"
              aria-label="Close"
            >
              <X className="w-4 h-4 text-gray-700" />
            </button>
            {activePin.thumbnail && (
              <div className="relative w-full h-36">
                <Image
                  src={activePin.thumbnail}
                  alt={activePin.title}
                  fill
                  className="object-cover"
                  sizes="288px"
                />
              </div>
            )}
            <div className="p-3">
              <p className="text-sm font-semibold text-gray-900 line-clamp-2 mb-1">
                {activePin.title}
              </p>
              <p className="text-sm font-bold text-blue-600 mb-2">
                {formatPrice(activePin.price, activePin.currency)}
              </p>
              <Link
                href={`/classified/find/${activePin.slug}`}
                className="block text-center bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-1.5 rounded-lg transition-colors"
              >
                {t("viewAd")}
              </Link>
            </div>
          </div>
        )}
      </div>
    </APIProvider>
  );
}
