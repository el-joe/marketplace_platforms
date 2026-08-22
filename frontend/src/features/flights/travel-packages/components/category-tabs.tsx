"use client";

import { useRouter, usePathname, useSearchParams } from "next/navigation";
import { useCallback } from "react";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import type { TravelAvailableCategory } from "../../helpers/types";

type Props = {
  categories: TravelAvailableCategory[];
  activeCategoryId: string | null;
};

export default function CategoryTabs({ categories, activeCategoryId }: Props) {
  const t = useTranslations("flights");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const setCategory = useCallback(
    (id: string | null) => {
      const params = new URLSearchParams(searchParams.toString());
      if (id) {
        params.set("category", id);
      } else {
        params.delete("category");
      }
      params.delete("page");
      router.push(`${pathname}?${params.toString()}`);
    },
    [router, pathname, searchParams],
  );

  return (
    <div className="flex items-center gap-2 overflow-x-auto pb-1">
      <button
        onClick={() => setCategory(null)}
        className={`shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-colors border ${
          !activeCategoryId
            ? "bg-blue-3 text-white border-blue-3"
            : "bg-white text-gray border-border hover:border-blue-3"
        }`}
      >
        {t("allTrips")}
      </button>

      {categories.map((cat) => (
        <button
          key={cat.id}
          onClick={() => setCategory(cat.id)}
          className={`shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-colors border ${
            activeCategoryId === cat.id
              ? "bg-blue-3 text-white border-blue-3"
              : "bg-white text-gray border-border hover:border-blue-3"
          }`}
        >
          {cat.icon && <span>{cat.icon}</span>}
          {locale === "ar" ? cat.name_ar : cat.name_en}
          {cat.package_count > 0 && (
            <span
              className={`text-xs px-1.5 py-0.5 rounded-full ${
                activeCategoryId === cat.id ? "bg-white/20" : "bg-gray-2"
              }`}
            >
              {cat.package_count}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}
