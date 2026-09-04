"use client";

import React, { useState } from "react";
import { ChevronDown, ChevronRight, ChevronLeft } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import { Select } from "@/src/components/ui/base-inputs/select";
import { IClassifiedCategoriesList } from "./helpers/types";

interface BreadcrumbAndHeaderProps {
  totalCount: number;
  onSortChange?: (sort: string) => void;
  category: IClassifiedCategoriesList | null;
}

export default function BreadcrumbAndHeader({
  totalCount = 0,
  onSortChange,
  category,
}: BreadcrumbAndHeaderProps) {
  const t = useTranslations("classified");
  const locale = useLocale();
  const [selectedSort, setSelectedSort] = useState("relevant");

  const SORT_OPTIONS = [
    { value: "relevant", label: t("sortRelevant") },
    { value: "newest", label: t("sortNewest") },
    { value: "price_asc", label: t("sortPriceAsc") },
    { value: "price_desc", label: t("sortPriceDesc") },
  ];

  return (
    <div className="w-full mb-4">
      {/* Breadcrumb */}
      <Breadcrumb
        list={
          !!category
            ? [
                { label: t("home"), href: "/" },
                { label: t("classifieds"), href: "/classified" },
                {
                  label: category?.name[locale],
                  href: `/classified/${category?.id}`,
                },
              ]
            : [
                { label: t("home"), href: "/" },
                { label: t("classifieds"), href: "/classified" },
              ]
        }
      />

      {/* Title & Sort Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
        <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug">
          {!!category?.name[locale]
            ? t("pageTitle", {
                category: category?.name[locale],
                count: totalCount.toLocaleString(),
              })
            : t("globalPageTitle", {
                count: totalCount.toLocaleString(),
              })}
        </h1>

        {/* Sort Select */}
        <Select
          items={SORT_OPTIONS}
          value={selectedSort}
          onValueChange={(value) => {
            setSelectedSort(value || "relevant");
            onSortChange?.(value || "relevant");
          }}
          triggerClass="w-42!"
        />
      </div>
    </div>
  );
}
