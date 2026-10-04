import { getTranslations } from "next-intl/server";
import React from "react";
import { getCategoriesTreeService } from "./api/get";
import SideList from "./side-list";
import MainContent from "./main-content";

export default async function NawyOptions() {
  const t = await getTranslations("nawy-options");
  const categories = await getCategoriesTreeService();
  if (categories.length === 0) {
    return <div className="container">{t("noCategories")}</div>;
  }
  return (
    <div className="container">
      <div className="flex gap-2">
        <SideList categories={categories} />
        <MainContent categories={categories} />
      </div>
    </div>
  );
}
