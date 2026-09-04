"use client";

import React, { useState } from "react";
import Image from "next/image";
import {
  Heart,
  MessageCircle,
  Phone,
  MapPin,
  Camera,
  CheckCircle2,
  Calendar,
  Fuel,
  Gauge,
  Car,
} from "lucide-react";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import Price from "@/src/components/shared/Price";
import { Item } from "./helpers/types";
import { Separator } from "@/src/components/ui/separator";
import { Link } from "@/i18n/navigation";
const specs = [
  { label: "2026" },
  { label: "Kia" },
  { label: "Sportage" },
  { label: "EX" },
  { label: "New" },
  { label: "2 km" },
  { label: "Gasoline" },
  { label: "Dealership" },
  { label: "Dummy data" },
];

interface ClassifiedCardProps {
  listing: Item;
}

export default function ClassifiedCard({ listing }: ClassifiedCardProps) {
  const t = useTranslations("classified");
  const locale = useLocale();
  const [isFavorite, setIsFavorite] = useState(false);
  const [phoneRevealed, setPhoneRevealed] = useState(false);

  const displayPhone = "0111111111 (dummy data)";

  const title = locale === "ar" ? listing?.title_ar : listing.title_en;
  const timeAgo = listing.created_at;
  const location = listing.location;
  const type = listing.listing_purpose;
  const currency = listing.currency;
  return (
    <Link
      href={`/classified/find/${listing.slug}`}
      className="w-full bg-white border block border-gray-200 rounded-xl p-3 sm:p-4 mb-4 hover:shadow-md transition-shadow"
    >
      <div className="flex flex-col md:flex-row gap-4 items-stretch">
        {/* Gallery / Image Side */}
        <div className="relative w-full md:w-[280px] lg:w-[310px] aspect-16/10 md:aspect-auto md:h-48 shrink-0 rounded-lg overflow-hidden bg-gray-100 select-none">
          <Image
            src={listing.images[0]?.url}
            alt={title}
            fill
            className="object-cover transition-transform duration-300 hover:scale-105"
            sizes="(max-width: 768px) 100vw, 310px"
          />
          {/* Verified User Badge */}
          {true && (
            <div className="absolute top-2.5 start-2.5 bg-[#0066cc]/90 text-white text-[11px] font-medium px-2 py-0.5 rounded-full flex items-center gap-1 shadow-xs">
              <CheckCircle2 className="w-3 h-3 text-white" />
              <span>{t("verifiedUser")} (dummy data)</span>
            </div>
          )}

          {/* Bottom Bar: Promo badge, dots, and photos counter */}
          <div className="absolute bottom-2 inset-x-2 flex items-center justify-between pointer-events-none">
            {/* Photos count pill */}
            <div className="bg-black/60 backdrop-blur-xs text-white text-[10px] px-2 py-0.5 rounded-sm flex items-center gap-1">
              <span>{listing.images.length}</span>
              <Camera className="w-3 h-3" />
            </div>
          </div>
        </div>

        {/* Details Side */}
        <div className="flex-1 flex flex-col justify-between min-w-0">
          <div>
            {/* Top row: Time ago and Price */}
            <Price currentPrice={listing.price} currency={currency} />

            {/* Title */}
            <h2 className="text-sm sm:text-base font-bold text-gray-900 leading-snug line-clamp-2 hover:text-blue-600 transition-colors cursor-pointer mb-2">
              {title}
            </h2>

            {/* Specifications row */}
            <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-gray-600">
              {specs.map((spec, i) => {
                return (
                  <React.Fragment key={i}>
                    <span className="inline-flex items-center gap-1">
                      {i === 0 && (
                        <Calendar className="w-3 h-3 text-gray-400" />
                      )}
                      {i === 1 && <Car className="w-3 h-3 text-gray-400" />}
                      {spec.label.includes("km") && (
                        <Gauge className="w-3 h-3 text-gray-400" />
                      )}
                      {spec.label.includes("Gasoline") && (
                        <Fuel className="w-3 h-3 text-gray-400" />
                      )}
                      <span>{spec.label}</span>
                    </span>
                    {i < specs.length - 1 && (
                      <span className="text-gray-300">•</span>
                    )}
                  </React.Fragment>
                );
              })}
            </div>
            <Separator className="my-3" />
            <div className="flex justify-between items-cen">
              {/* Location */}
              <div className="flex items-center gap-1 text-gray-500 mt-2">
                <MapPin className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                <span>{location}</span>
              </div>
              <span className="text-gray-400">
                {new Date(timeAgo).toLocaleDateString()}
              </span>
            </div>
          </div>

          {/* Bottom row: Category & Action buttons */}
          <div className="pt-3 mt-3 flex flex-wrap items-center justify-between gap-3">
            <span className="text-gray-400">{t("for", { type })}</span>

            <div className="flex items-center gap-2">
              {/* Phone call button */}
              <button
                type="button"
                onClick={() => setPhoneRevealed(!phoneRevealed)}
                className="bg-[#0066cc] hover:bg-[#0052a3] text-white rounded-md px-3.5 py-1.5 text-sm font-semibold flex items-center gap-1.5 transition-colors shadow-2xs cursor-pointer"
              >
                <Phone className="w-3.5 h-3.5 rtl:rotate-240" />
                <span dir="ltr">{displayPhone}</span>
              </button>

              {/* Chat button */}
              <button
                type="button"
                className="border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-md px-3.5 py-1.5 text-sm font-medium flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <MessageCircle className="w-3.5 h-3.5 text-teal-600" />
                <span>{t("chat")}</span>
              </button>

              {/* Favorite / Heart button */}
              <button
                type="button"
                onClick={() => setIsFavorite(!isFavorite)}
                className={`w-8 h-8 rounded-md border flex items-center justify-center transition-colors cursor-pointer ${
                  isFavorite
                    ? "border-red-200 bg-red-50 text-red-500"
                    : "border-gray-300 hover:bg-gray-50 text-gray-400 hover:text-red-500"
                }`}
                aria-label="Add to favorites"
              >
                <Heart
                  className={`w-4 h-4 ${isFavorite ? "fill-red-500" : ""}`}
                />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Link>
  );
}
