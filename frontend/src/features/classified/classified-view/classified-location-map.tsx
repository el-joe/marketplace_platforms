"use client";

import { APIProvider, Map, AdvancedMarker } from "@vis.gl/react-google-maps";
import { useTranslations } from "next-intl";

interface ClassifiedLocationMapProps {
  latitude: number;
  longitude: number;
}

export default function ClassifiedLocationMap({
  latitude,
  longitude,
}: ClassifiedLocationMapProps) {
  const t = useTranslations("classifiedLocationMap");
  const center = { lat: latitude, lng: longitude };

  return (
    <section className="mt-6 pt-6 border-t border-gray-200/80">
      <h2 className="text-base font-semibold text-gray-800 mb-3">
        {t("locationOnMap")}
      </h2>
      <div
        style={{
          height: 280,
          borderRadius: 12,
          overflow: "hidden",
          border: "1px solid #e5e7eb",
        }}
      >
        <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY!}>
          <Map
            defaultCenter={center}
            defaultZoom={14}
            mapId="classified-detail-map"
            gestureHandling="cooperative"
            disableDefaultUI={false}
          >
            <AdvancedMarker position={center} />
          </Map>
        </APIProvider>
      </div>
      <p className="mt-2 text-xs text-gray-400">{t("locationApproximate")}</p>
    </section>
  );
}
