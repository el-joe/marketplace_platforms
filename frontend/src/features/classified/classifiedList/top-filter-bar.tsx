"use client";

import React, { useState } from "react";
import { ChevronDown, SlidersHorizontal } from "lucide-react";
import { useTranslations } from "next-intl";

interface TopFilterBarProps {
  onFilterChange?: (filterKey: string, value: string) => void;
}

export default function TopFilterBar({ onFilterChange }: TopFilterBarProps) {
  const t = useTranslations("classified");
  const [city, setCity] = useState("");
  const [district, setDistrict] = useState("");
  const [price, setPrice] = useState("");

  const CITIES = [
    { value: "", label: t("allCities") },
    { value: "cairo", label: t("cairo") },
    { value: "giza", label: t("giza") },
    { value: "alex", label: t("alexandria") },
    { value: "minya", label: t("minya") },
  ];

  const DISTRICTS = [
    { value: "", label: t("allNeighbourhoods") },
    { value: "tagamoa", label: t("fifthSettlement") },
    { value: "shorouk", label: t("elShorouk") },
    { value: "sheikh-zayed", label: t("sheikhZayed") },
    { value: "nasr-city", label: t("nasrCity") },
  ];

  const PRICES = [
    { value: "", label: t("allPrices") },
    { value: "0-500k", label: t("under500k") },
    { value: "500k-1m", label: t("priceRange500k1m") },
    { value: "1m-2m", label: t("priceRange1m2m") },
    { value: "2m+", label: t("above2m") },
  ];

  return (
    <div className="w-full bg-white border border-gray-200 rounded-lg shadow-2xs mb-4">
      <div className="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 rtl:divide-x-reverse">
        {/* City / المدينة */}
        <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
          <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
            <span>{t("city")}</span>
            <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
          </div>
          <select
            value={city}
            onChange={(e) => {
              setCity(e.target.value);
              onFilterChange?.("city", e.target.value);
            }}
            className="w-full bg-transparent text-xs text-gray-500 outline-hidden cursor-pointer appearance-none"
          >
            <option value="" disabled hidden>
              {t("searchForCity")}
            </option>
            {CITIES.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
        </div>

        {/* Neighbourhood / المنطقة */}
        <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
          <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
            <span>{t("neighbourhood")}</span>
            <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
          </div>
          <select
            value={district}
            onChange={(e) => {
              setDistrict(e.target.value);
              onFilterChange?.("district", e.target.value);
            }}
            className="w-full bg-transparent text-xs text-gray-500 outline-hidden cursor-pointer appearance-none"
          >
            <option value="" disabled hidden>
              {t("selectNeighbourhood")}
            </option>
            {DISTRICTS.map((d) => (
              <option key={d.value} value={d.value}>
                {d.label}
              </option>
            ))}
          </select>
        </div>

        {/* Price / السعر */}
        <div className="relative p-2.5 sm:p-3 flex flex-col justify-center cursor-pointer hover:bg-gray-50/70 transition-colors">
          <div className="flex items-center justify-between text-xs font-semibold text-gray-700 mb-0.5">
            <span>{t("price")}</span>
            <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
          </div>
          <select
            value={price}
            onChange={(e) => {
              setPrice(e.target.value);
              onFilterChange?.("price", e.target.value);
            }}
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
  );
}
