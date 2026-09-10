import { Link } from "@/i18n/navigation";
import { fetchInstance } from "@/src/lib/utils";
import { getTranslations } from "next-intl/server";
import React from "react";

const PopularSearchesTags = async () => {
  const t = await getTranslations();

  let keywords: string[] = [];
  try {
    const { data } = await fetchInstance<{ data: { keywords: string[] } }>(
      "/search/popular",
    );
    keywords = data.keywords ?? [];
  } catch {
    keywords = [];
  }

  if (keywords.length === 0) {
    return null;
  }

  return (
    <div className="container pb-5 pt-8">
      <h2 className="mb-3">{t("popularSearches")}</h2>
      <div className="flex flex-wrap gap-2">
        {keywords.map((keyword, i) => (
          <Link
            href={`/search/?q=${encodeURIComponent(keyword)}`}
            key={i}
            className="bg-gray-2 rounded-md text-light text-xs md:text-sm lg:text-base px-2 py-1"
          >
            {keyword}
          </Link>
        ))}
      </div>
    </div>
  );
};

export default PopularSearchesTags;
