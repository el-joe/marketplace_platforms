"use client";

import React, { useState } from "react";
import {
  Heart,
  Share2,
  Bell,
  Star,
  ChevronRight,
  Tag,
  Gauge,
  Fuel,
  Building2,
  Check,
} from "lucide-react";
import { IClassified } from "./helpers/types";
import useLocale from "@/src/hooks/use-locale";
import Price from "@/src/components/shared/Price";

interface ClassifiedHeaderDetailsProps {
  listing: IClassified;
}

export default function ClassifiedHeaderDetails({
  listing,
}: ClassifiedHeaderDetailsProps) {
  const [isNotified, setIsNotified] = useState(false);
  const [copied, setCopied] = useState(false);
  const locale = useLocale();

  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: listing.title[locale],
          text: `${listing.title[locale]} - ${listing.price.toLocaleString()} ${listing.currency}`,
          url: window.location.href,
        });
      } catch {
        // User cancelled share
      }
    } else {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const getSpecIcon = (type: string) => {
    switch (type) {
      case "tag":
        return <Tag className="w-3.5 h-3.5 text-gray-500" />;
      case "speedometer":
        return <Gauge className="w-3.5 h-3.5 text-gray-500" />;
      case "gas":
        return <Fuel className="w-3.5 h-3.5 text-gray-500" />;
      case "dealership":
        return <Building2 className="w-3.5 h-3.5 text-gray-500" />;
      default:
        return null;
    }
  };

  return (
    <div className="w-full bg-white pt-4 pb-2 border-b border-gray-100">
      {/* Top Row: Price, Price Drop Link & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3 mb-2.5">
        {/* Left: Price and notification link */}
        <div className="flex items-baseline flex-wrap gap-2.5 sm:gap-3">
          <Price
            currentPrice={listing.price / 100}
            currency={listing.currency}
          />
          {/* <div className="text-2xl sm:text-3xl font-extrabold text-red-600 tracking-tight">
            {listing.price.toLocaleString()} {listing.currency}
          </div> */}
          <button
            onClick={() => setIsNotified(!isNotified)}
            className="text-xs sm:text-sm text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 font-medium transition-colors"
          >
            <Bell
              className={`w-3.5 h-3.5 ${isNotified ? "fill-blue-600" : ""}`}
            />
            <span>
              {isNotified
                ? "Alert set for price drops"
                : "Notify me if price drops"}
            </span>
          </button>
        </div>

        {/* Right: Favourite & Share */}
        <div className="flex items-center gap-3">
          <button className="flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-red-500 transition-colors py-1 px-2 rounded-lg hover:bg-gray-50">
            <Heart
              className={`w-4 h-4 transition-colors ${
                true ? "fill-red-500 text-red-500" : "text-gray-600"
              }`}
            />
            <span>Favourite (5 dummy_data)</span>
          </button>

          <button
            onClick={handleShare}
            className="flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors py-1 px-2 rounded-lg hover:bg-gray-50"
          >
            {copied ? (
              <Check className="w-4 h-4 text-emerald-600" />
            ) : (
              <Share2 className="w-4 h-4 text-gray-600" />
            )}
            <span>{copied ? "Link Copied!" : "Share"}</span>
          </button>
        </div>
      </div>

      {/* Main Title (Arabic) */}
      <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug mb-1">
        {listing.title[locale]}
      </h1>

      {/* English / Model Subtitle */}
      <p className="text-sm font-medium text-gray-600 mb-2">
        {listing.category.name[locale]}
      </p>

      {/* Rating badge & Reviews link */}
      <div className="flex items-center gap-2 mb-3">
        <div className="inline-flex items-center gap-1 bg-emerald-700 text-white text-xs font-bold px-1.5 py-0.5 rounded">
          <Star className="w-3 h-3 fill-white text-white" />
          <span>4 dummy_data</span>
        </div>
        <button className="text-xs sm:text-sm text-gray-700 hover:text-blue-600 font-medium flex items-center hover:underline">
          <span>{listing.views_count} Reviews dummy_data</span>
          <ChevronRight className="w-3.5 h-3.5 rtl:rotate-180" />
        </button>
      </div>

      {/* Quick Specs Badges */}
      <div className="flex flex-wrap items-center gap-3 sm:gap-4 text-xs sm:text-sm text-gray-700">
        {[
          { type: "tag", label: "New" },
          { type: "speedometer", label: "2 km" },
          { type: "gas", label: "Gasoline" },
          { type: "dealership", label: "Dealership" },
          { type: "dealership", label: "dummy_data" },
        ].map((spec, i) => (
          <div
            key={i}
            className="flex items-center gap-1.5 bg-gray-50 px-2 py-1 rounded border border-gray-100"
          >
            {getSpecIcon(spec.type)}
            <span className="font-medium text-gray-800">{spec.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
