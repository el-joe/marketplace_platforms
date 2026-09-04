"use client";

import React, { useState } from "react";
import { ChevronDown, Camera } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { IClassifiedCategoriesList } from "./helpers/types";

type props = {
  categories: IClassifiedCategoriesList[];
  selectedCtg?: string | null;
};

export default function ClassifiedSidebar({ categories, selectedCtg }: props) {
  const t = useTranslations("classified");
  const locale = useLocale();
  const [showMoreCategories, setShowMoreCategories] = useState(false);

  return (
    <aside className="w-full lg:w-[280px] xl:w-[300px] shrink-0 space-y-4 hidden lg:block">
      {/* 1. Categories Card */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-2xs">
        <h3 className="text-sm font-bold text-gray-900 mb-2">
          {t("categories")}
        </h3>

        <div className="text-xs space-y-2">
          <Link
            href="/classified"
            className="block text-gray-500 hover:text-blue-600 transition-colors"
          >
            {t("allCategories")}
          </Link>

          {/* <div className="font-bold text-gray-900 pt-1">{t("autos")}</div> */}

          <div className="space-y-2 ps-2 pt-1">
            {categories
              .slice(0, showMoreCategories ? categories.length : 5)
              .map((cat) => (
                <Link
                  href={`/classified/${cat.id}`}
                  key={cat.id}
                  className="flex items-center justify-between text-xs text-gray-600 hover:text-blue-600 cursor-pointer transition-colors"
                >
                  <span
                    className={
                      cat.id === selectedCtg ? "font-bold text-gray-900" : ""
                    }
                  >
                    {cat.name[locale]}
                  </span>
                </Link>
              ))}
          </div>

          {categories.length > 5 && (
            <button
              type="button"
              onClick={() => setShowMoreCategories(!showMoreCategories)}
              className="text-xs text-blue-600 hover:underline flex items-center gap-1 pt-1 cursor-pointer"
            >
              <span>{showMoreCategories ? t("viewLess") : t("viewMore")}</span>
              <ChevronDown
                className={`w-3.5 h-3.5 transition-transform ${
                  showMoreCategories ? "rotate-180" : ""
                }`}
              />
            </button>
          )}
        </div>
      </div>

      {/* 3. Post Your Ad CTA Card */}
      <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-2xs text-center">
        <h3 className="text-sm font-bold text-gray-900">
          {t("wantToSeeYourStuff")}
        </h3>
        <p className="text-xs text-gray-500 mt-1 leading-relaxed">
          {t("wantToSeeYourStuffDesc")}
        </p>
        <Link href={`/profile`}>
          <button
            type="button"
            className="mt-4 w-full bg-[#f58220] hover:bg-[#e07113] active:bg-[#c9620b] text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-lg flex items-center justify-center gap-2 shadow-sm transition-colors cursor-pointer"
          >
            <Camera className="w-4 h-4" />
            <span>{t("addNewListing")}</span>
          </button>
        </Link>
      </div>
    </aside>
  );
}
