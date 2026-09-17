"use client";

import React, { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { Heart } from "lucide-react";
import { useTranslations } from "next-intl";
import { RecommendedClassifiedItem } from "./types";

interface ClassifiedRecommendedProps {
  items: RecommendedClassifiedItem[];
}

export default function ClassifiedRecommended({
  items,
}: ClassifiedRecommendedProps) {
  const t = useTranslations("classified");
  const [favorites, setFavorites] = useState<Record<string, boolean>>({});

  const toggleFavorite = (id: string, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setFavorites((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  return (
    <section className="w-full mt-10 pt-6 border-t border-gray-200">
      <h2 className="text-lg sm:text-xl font-bold text-gray-900 mb-4">
        Recommended For You
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {items.map((item) => {
          const isFav = favorites[item.id] ?? item.isFavorite;

          return (
            <Link
              key={item.id}
              href={`/classified/find/${item.slug || item.id}`}
              className="group bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-3.5 hover:shadow-md hover:border-gray-300 transition-all"
            >
              {/* Image */}
              <div className="relative w-28 sm:w-36 h-20 sm:h-24 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                <Image
                  src={item.image}
                  alt={item.title}
                  fill
                  className="object-cover group-hover:scale-105 transition-transform duration-300"
                  sizes="150px"
                />
              </div>

              {/* Details */}
              <div className="flex-1 min-w-0 flex flex-col justify-between self-stretch py-0.5">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="font-bold text-xs sm:text-sm text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                    {item.title}
                  </h3>

                  <button
                    onClick={(e) => toggleFavorite(item.id, e)}
                    className="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                    aria-label={t("addToFavorites")}
                  >
                    <Heart
                      className={`w-4 h-4 ${
                        isFav ? "fill-red-500 text-red-500" : ""
                      }`}
                    />
                  </button>
                </div>

                <p className="text-[11px] sm:text-xs text-gray-500 line-clamp-1">
                  {item.specs}
                </p>

                {item.location && (
                  <span className="text-[11px] sm:text-xs text-gray-400 truncate">
                    {item.location}
                  </span>
                )}
              </div>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
