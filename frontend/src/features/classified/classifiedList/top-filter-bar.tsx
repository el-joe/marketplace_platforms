"use client";

import React, { useState } from "react";
import {
  ChevronDown,
  LocateFixedIcon,
  SlidersHorizontal,
  XIcon,
} from "lucide-react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";
import { ClassifiedCity } from "./api/get";

const RADIUS_OPTIONS = [5, 10, 25, 50];
const DEFAULT_RADIUS = "25";
const LOCATION_PARAMS = ["city_id", "lat", "lng", "radius_km"];

interface TopFilterBarProps {
  cities: ClassifiedCity[];
}

export default function TopFilterBar({ cities }: TopFilterBarProps) {
  const t = useTranslations("classified");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [price, setPrice] = useState("");
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);

  const cityId = searchParams.get("city_id") ?? "";
  const nearMeActive = !!searchParams.get("lat") && !!searchParams.get("lng");
  const radius = searchParams.get("radius_km") ?? DEFAULT_RADIUS;

  const PRICES = [
    { value: "", label: t("allPrices") },
    { value: "0-500k", label: t("under500k") },
    { value: "500k-1m", label: t("priceRange500k1m") },
    { value: "1m-2m", label: t("priceRange1m2m") },
    { value: "2m+", label: t("above2m") },
  ];

  // A city and a point+radius are alternative ways to narrow by place, so setting one clears the other.
  const applyLocation = (next: Record<string, string>) => {
    const params = new URLSearchParams(searchParams.toString());
    LOCATION_PARAMS.forEach((key) => params.delete(key));
    Object.entries(next).forEach(
      ([key, value]) => value && params.set(key, value),
    );
    const qs = params.toString();
    router.push(qs ? `${pathname}?${qs}` : pathname);
  };

  const useMyLocation = () => {
    if (!navigator.geolocation) {
      setLocationError(t("locationUnavailable"));
      return;
    }
    setLocating(true);
    setLocationError(null);
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setLocating(false);
        applyLocation({
          lat: coords.latitude.toFixed(6),
          lng: coords.longitude.toFixed(6),
          radius_km: radius,
        });
      },
      (err) => {
        setLocating(false);
        setLocationError(
          err.code === err.PERMISSION_DENIED
            ? t("locationDenied")
            : t("locationUnavailable"),
        );
      },
      { enableHighAccuracy: true, timeout: 10000 },
    );
  };

  return (
    <div className="mb-4">
      <div className="w-full bg-white border border-gray-200 rounded-lg shadow-2xs">
        <div className="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 rtl:divide-x-reverse">
          {/* City */}
          <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
            <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
              <span>{t("city")}</span>
              <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
            </div>
            <select
              value={cityId}
              onChange={(e) =>
                applyLocation(e.target.value ? { city_id: e.target.value } : {})
              }
              className="w-full bg-transparent text-xs text-gray-500 outline-hidden cursor-pointer appearance-none"
            >
              <option value="">{t("allCities")}</option>
              {cities.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name} ({c.count})
                </option>
              ))}
            </select>
          </div>

          {/* Near me: current location + radius */}
          <div className="relative p-2.5 sm:p-3 flex flex-col justify-center hover:bg-gray-50/70 transition-colors">
            <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
              <span className="flex items-center gap-1">
                <LocateFixedIcon className="w-3 h-3 text-gray-500" />
                {t("nearMe")}
              </span>
              {nearMeActive && (
                <button
                  type="button"
                  aria-label={t("allCities")}
                  onClick={() => applyLocation({})}
                  className="text-gray-400 hover:text-gray-700"
                >
                  <XIcon className="w-3.5 h-3.5" />
                </button>
              )}
            </div>
            {nearMeActive ? (
              <select
                value={radius}
                onChange={(e) =>
                  applyLocation({
                    lat: searchParams.get("lat") ?? "",
                    lng: searchParams.get("lng") ?? "",
                    radius_km: e.target.value,
                  })
                }
                className="w-full bg-transparent text-xs text-blue-600 font-semibold outline-hidden cursor-pointer appearance-none"
              >
                {RADIUS_OPTIONS.map((km) => (
                  <option key={km} value={km}>
                    {t("radiusKm", { km })}
                  </option>
                ))}
              </select>
            ) : (
              <button
                type="button"
                onClick={useMyLocation}
                disabled={locating}
                className="text-start text-xs text-gray-500 truncate disabled:opacity-60"
              >
                {t("nearMeHint")}
              </button>
            )}
          </div>

          {/* Price / السعر */}
          <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
            <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
              <span>{t("price")}</span>
              <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
            </div>
            <select
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              className="w-full bg-transparent text-xs text-gray-500 outline-hidden cursor-pointer appearance-none"
            >
              <option value="" disabled hidden>
                {t("selectPrice")}
              </option>
              {PRICES.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </select>
          </div>

          {/* Filters / فلترة */}
          <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
            <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
              <span className="flex items-center gap-1">
                <SlidersHorizontal className="w-3 h-3 text-gray-500" />
                {t("filters")}
              </span>
              <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
            </div>
            <span className="text-xs text-gray-500 truncate">
              {t("moreOptions")}
            </span>
          </div>
        </div>
      </div>
      {locationError && (
        <p className="mt-1.5 text-xs text-red-600">{locationError}</p>
      )}
    </div>
  );
}
