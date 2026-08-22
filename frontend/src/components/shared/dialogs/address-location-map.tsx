"use client";

import { Map, type MapCameraChangedEvent } from "@vis.gl/react-google-maps";
import { MapPin } from "lucide-react";
import { cn } from "@/src/lib/utils";
import { LatLng } from "../maps/use-location-map";

type Props = {
  center: LatLng;
  onCenterChanged: (center: LatLng) => void;
  pinLabel?: React.ReactNode;
  className?: string;
};

export default function AddressLocationMap({
  center,
  onCenterChanged,
  pinLabel,
  className,
}: Props) {
  const handleCameraChanged = (event: MapCameraChangedEvent) => {
    onCenterChanged(event.detail.center);
  };

  return (
    <div className={cn("relative h-80 w-full overflow-hidden", className)}>
      <Map
        center={center}
        defaultZoom={15}
        gestureHandling="greedy"
        disableDefaultUI={false}
        fullscreenControl={false}
        streetViewControl={false}
        mapTypeControl={false}
        onCameraChanged={handleCameraChanged}
      />

      <div className="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full flex flex-col items-center">
        {pinLabel && (
          <span className="mb-1 rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background whitespace-nowrap">
            {pinLabel}
          </span>
        )}
        <MapPin className="size-8 fill-foreground text-foreground" />
      </div>
    </div>
  );
}
