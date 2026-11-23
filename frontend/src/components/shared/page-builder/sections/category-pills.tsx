"use client";
import React from "react";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { Block } from "../types";
import SectionTitle from "./section-title";

/**
 * Page-builder `category_pills` block: a horizontal row of category chips.
 * The backend fills `categories` with the block's hand-picked categories, or —
 * when none are picked — the categories flagged "Featured (homepage)" in admin.
 */
export const CategoryPills = ({ data }: { data: Block }) => {
  const locale = useLocale();
  const categories = data?.categories ?? [];

  if (categories.length === 0) return null;

  const title = data?.title;
  const hasTitle = !!(title?.en || title?.ar);

  return (
    <div>
      {hasTitle && <SectionTitle title={title!} />}
      <ul className="flex gap-3 overflow-x-auto pb-2 [scrollbar-width:none]">
        {categories.map((category) => {
          const name = category.name?.[locale] || category.name?.en || "";
          return (
            <li key={category.id} className="shrink-0">
              <Link
                href={`/${category.slug}`}
                className="flex w-24 flex-col items-center gap-2 text-center lg:w-28"
              >
                <span className="flex size-20 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-gray-50 lg:size-24">
                  {category.image_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={category.image_url}
                      alt={name}
                      loading="lazy"
                      className="size-full object-cover"
                    />
                  ) : (
                    <span className="text-2xl font-bold text-gray-400">
                      {name.charAt(0)}
                    </span>
                  )}
                </span>
                <span className="line-clamp-2 text-xs font-medium text-gray-800 lg:text-sm">
                  {name}
                </span>
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
};

export default CategoryPills;
