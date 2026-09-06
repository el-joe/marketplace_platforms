"use client";

import Image from "next/image";
import Link from "next/link";
import { Heart } from "lucide-react";
import { ClassifiedItem } from "../classifiedList/helpers/types";
import { useTranslations } from "next-intl";

interface ClassifiedRecommendedProps {
  items: ClassifiedItem[];
}

export default function ClassifiedRecommended({
  items,
}: ClassifiedRecommendedProps) {
  const t = useTranslations("classified");
  return (
    <section className="w-full mt-10 pt-6 border-t border-gray-200">
      <h2 className="text-lg sm:text-xl font-bold text-gray-900 mb-4">
        {t("recommendedForYou")}
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {items.map((item) => {
          const isFav = false;

          return (
            <Link
              key={item.listing_id}
              href={`/classified/find/${item.slug}`}
              className="group bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-3.5 hover:shadow-md hover:border-gray-300 transition-all"
            >
              {/* Image */}
              <div className="relative w-28 sm:w-36 h-20 sm:h-24 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                <Image
                  src={item.thumbnail}
                  alt={item.title_en}
                  fill
                  className="object-cover group-hover:scale-105 transition-transform duration-300"
                  sizes="150px"
                />
              </div>

              {/* Details */}
              <div className="flex-1 min-w-0 flex flex-col justify-between self-stretch py-0.5">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="font-bold text-xs sm:text-sm text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                    {item.title_en}
                  </h3>

                  <button
                    className="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                    aria-label="Add to favorites"
                  >
                    <Heart
                      className={`w-4 h-4 ${
                        isFav ? "fill-red-500 text-red-500" : ""
                      }`}
                    />
                  </button>
                </div>

                <p className="text-[11px] sm:text-xs text-gray-500 line-clamp-1">
                  {/* {item.specs_en  } */}
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
