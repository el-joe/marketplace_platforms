"use client";

import React, { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import useRegion from "@/src/hooks/use-region";
import { getCountriesService } from "@/src/services/countries";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import { Select } from "@/src/components/ui/base-inputs/select";
import { IClassifiedCategoryRef } from "./helpers/types";

interface BreadcrumbAndHeaderProps {
  totalCount: number;
  onSortChange?: (sort: string) => void;
  /** Section > sub-category trail of the resolved category (empty on the landing page). */
  trail: IClassifiedCategoryRef[];
}

export default function BreadcrumbAndHeader({
  totalCount = 0,
  onSortChange,
  trail: categoryTree,
}: BreadcrumbAndHeaderProps) {
  const t = useTranslations("classified");
  const locale = useLocale();
  const region = useRegion();
  const [selectedSort, setSelectedSort] = useState("relevant");
  const { data: countriesData } = useQuery({
    queryKey: ["countries", locale],
    queryFn: getCountriesService,
    staleTime: 60 * 60 * 1000,
  });
  const countryName = countriesData?.data?.find(
    (country) => country.site_code === region,
  )?.name;
  const sectionName = categoryTree[categoryTree.length - 1]?.name[locale];
  const count = totalCount.toLocaleString();

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
        list={[
          { label: t("home"), href: "/" },
          { label: t("classifieds"), href: "/classified" },
          ...categoryTree.map((cat) => ({
            label: cat.name[locale],
            href: `/classified/${cat.slug}`,
          })),
        ]}
      />

      {/* Title & Sort Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
        <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug">
          {sectionName
            ? countryName
              ? t("pageTitle", { category: sectionName, country: countryName, count })
              : t("pageTitleNoCountry", { category: sectionName, count })
            : t("globalPageTitle", { count })}
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
