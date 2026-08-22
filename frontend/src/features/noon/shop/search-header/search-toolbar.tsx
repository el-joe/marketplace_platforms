import { getTranslations } from "next-intl/server";

import MobileFiltersSheet from "../filter/mobile-view";
import SortSelect from "./sort-select";

type Props = {
  categoryName: string;
  resultsCount: number;
};

const ShopToolbar = async ({ categoryName, resultsCount }: Props) => {
  const t = await getTranslations("shop");

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border-color pb-4">
      <h1 className="text-sm font-medium lg:text-base">
        {resultsCount.toLocaleString()}+ {t("resultsFor")} &quot;{categoryName}
        &quot;
      </h1>
      <div className="flex items-center gap-2">
        <MobileFiltersSheet />
        <SortSelect />
      </div>
    </div>
  );
};

export default ShopToolbar;
