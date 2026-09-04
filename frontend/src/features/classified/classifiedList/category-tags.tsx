"use client";

import React, { useState } from "react";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { IClassifiedCategoriesList } from "./helpers/types";
import { Link } from "@/i18n/navigation";

interface CategoryTagsProps {
  categories: IClassifiedCategoriesList[];
  selectedCtg: string | null;
}

export default function CategoryTags({
  categories,
  selectedCtg,
}: CategoryTagsProps) {
  const t = useTranslations("classified");
  const locale = useLocale();
  const [showAll, setShowAll] = useState(false);

  return (
    <div className="w-full mb-4 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none flex-wrap">
      {categories.slice(0, showAll ? categories.length : 5).map((tag, idx) => {
        const label = tag.name[locale];
        const isSelected = selectedCtg === tag.id;
        return (
          <Link
            href={`/classified/${tag.id}`}
            key={idx}
            type="button"
            className={`text-xs px-3 py-1.5 rounded-md font-medium border transition-colors whitespace-nowrap cursor-pointer ${
              isSelected
                ? "border-blue-500 bg-blue-50/50 text-blue-600 shadow-2xs"
                : "border-blue-200/80 bg-white text-blue-600 hover:border-blue-400 hover:bg-blue-50/30"
            }`}
          >
            {label}
          </Link>
        );
      })}
      {categories.length > 5 && (
        <button
          type="button"
          onClick={() => setShowAll(!showAll)}
          className="text-xs px-3 py-1.5 rounded-md font-medium border border-blue-200/80 bg-white text-blue-600 hover:border-blue-400 hover:bg-blue-50/30 whitespace-nowrap cursor-pointer"
        >
          {showAll ? t("viewLess") : t("viewMore")}
        </button>
      )}
    </div>
  );
}
