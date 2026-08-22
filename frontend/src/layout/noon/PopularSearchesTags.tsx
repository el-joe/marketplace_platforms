import { Link } from "@/i18n/navigation";
import { popularSearchData } from "@/public/dummyData";
import { getTranslations } from "next-intl/server";
import React from "react";

const PopularSearchesTags = async () => {
  const t = await getTranslations();
  return (
    <div className="container pb-5 pt-8">
      <h2 className="mb-3">{t("popularSearches")}</h2>
      <div className="flex flex-wrap gap-2">
        {popularSearchData.map((popularSearch, i) => (
          <Link
            href={"/"}
            key={i}
            className="bg-gray-2 rounded-md text-light text-xs md:text-sm lg:text-base px-2 py-1"
          >
            {popularSearch}
          </Link>
        ))}
      </div>
    </div>
  );
};

export default PopularSearchesTags;
