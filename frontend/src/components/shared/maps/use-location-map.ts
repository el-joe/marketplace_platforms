"use client";

import { useCallback, useRef, useState } from "react";
import { useMapsLibrary } from "@vis.gl/react-google-maps";

export type LatLng = { lat: number; lng: number };

export type ResolvedAddress = {
  formattedAddress: string;
  mainText: string;
  secondaryText: string;
};

const DEFAULT_CENTER: LatLng = { lat: 25.1972, lng: 55.2744 };
const REVERSE_GEOCODE_DEBOUNCE_MS = 400;

function splitFormattedAddress(formattedAddress: string): {
  mainText: string;
  secondaryText: string;
} {
  const [mainText, ...rest] = formattedAddress.split(",");
  return {
    mainText: mainText?.trim() ?? formattedAddress,
    secondaryText: rest.join(",").trim(),
  };
}

export function useLocationMap(defaultCenter: LatLng = DEFAULT_CENTER) {
  const geocodingLibrary = useMapsLibrary("geocoding");
  const geocoderRef = useRef<google.maps.Geocoder | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [center, setCenter] = useState<LatLng>(defaultCenter);
  const [address, setAddress] = useState<ResolvedAddress | null>(null);
  const [isLocating, setIsLocating] = useState(false);
  const [isResolvingAddress, setIsResolvingAddress] = useState(false);

  const reverseGeocode = useCallback(
    (location: LatLng) => {
      if (!geocodingLibrary) return;

      if (!geocoderRef.current) {
        geocoderRef.current = new geocodingLibrary.Geocoder();
      }

      setIsResolvingAddress(true);
      geocoderRef.current
        .geocode({ location })
        .then(({ results }) => {
          const formattedAddress = results[0]?.formatted_address;
          if (formattedAddress) {
            setAddress({
              formattedAddress,
              ...splitFormattedAddress(formattedAddress),
            });
          }
        })
        .finally(() => setIsResolvingAddress(false));
    },
    [geocodingLibrary],
  );

  const handleCenterChanged = useCallback(
    (nextCenter: LatLng) => {
      setCenter(nextCenter);

      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(
        () => reverseGeocode(nextCenter),
        REVERSE_GEOCODE_DEBOUNCE_MS,
      );
    },
    [reverseGeocode],
  );

  const useCurrentLocation = useCallback(() => {
    if (!navigator.geolocation) return;

    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        const nextCenter = { lat: coords.latitude, lng: coords.longitude };
        setCenter(nextCenter);
        reverseGeocode(nextCenter);
        setIsLocating(false);
      },
      () => setIsLocating(false),
    );
  }, [reverseGeocode]);

  return {
    center,
    address,
    isLocating,
    isResolvingAddress,
    handleCenterChanged,
    useCurrentLocation,
  };
}
