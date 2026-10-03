"use client";
import React from "react";
import { ICategoryNavTree } from "./types";
import useLocale from "@/src/hooks/use-locale";
import { useQueryState } from "nuqs";
import { cn } from "@/src/lib/utils";
import { useTranslations } from "next-intl";

type Props = {
  categories: ICategoryNavTree[];
};

export default function SideList({ categories }: Props) {
  const locale = useLocale();
  const t = useTranslations("nawyOptions");
  const [selectedCategory, setSelectedCategory] = useQueryState("category", {
    history: "replace",
  });
  return (
    <div className="w-1/4 bg-[#f0f2f7] min-w-[90px] max-w-[425px]">
      <button
        className={cn(
          "max-w-[425px] min-w-[90px] flex items-center justify-center p-1 sm:p-2.5 text-center w-full text-xs sm:text-sm md:text-base",
          {
            "bg-white border-s-[3px] border-s-black": !selectedCategory,
          },
        )}
        onClick={() => setSelectedCategory(null)}
      >
        {t("justForYou")}
      </button>
      {categories.map((category) => (
        <button
          key={category.id}
          className={cn(
            "max-w-[425px] min-w-[90px] flex items-center justify-center p-1 sm:p-2.5 text-center w-full text-xs sm:text-sm md:text-base",
            {
              "bg-white border-s-[3px] border-s-black":
                selectedCategory === category.id,
            },
          )}
          onClick={() => setSelectedCategory(category.id)}
        >
          {category?.name?.[locale]}
        </button>
      ))}
    </div>
  );
}
