"use client";

import React, { useState } from "react";
import { X } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { IClassifiedCategoriesList } from "./helpers/types";
import useLocale from "@/src/hooks/use-locale";

type props = {
  selectedCtg?: IClassifiedCategoriesList | null;
};

export default function ActiveFiltersBar({ selectedCtg }: props) {
  const t = useTranslations("classified");
  const [hasFilter, setHasFilter] = useState(!!selectedCtg);
  const locale = useLocale();

  if (!hasFilter) return null;

  return (
    <div className="w-full bg-white border border-gray-200 rounded-lg px-4 py-2.5 mb-4 flex items-center gap-3 shadow-2xs">
      <Link
        href="/classified"
        type="button"
        className="text-xs text-blue-600 hover:underline font-medium cursor-pointer"
      >
        {t("reset")}
      </Link>

      <div className="flex items-center gap-2 flex-wrap">
        <div className="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200/80 text-gray-800 text-xs px-2.5 py-1 rounded-full transition-colors">
          <span>{selectedCtg?.name?.[locale]}</span>
          <button
            type="button"
            onClick={() => setHasFilter(false)}
            className="w-4 h-4 rounded-full bg-gray-700 text-white flex items-center justify-center hover:bg-gray-900 transition-colors"
          >
            <X className="w-2.5 h-2.5" />
          </button>
        </div>
      </div>
    </div>
  );
}
