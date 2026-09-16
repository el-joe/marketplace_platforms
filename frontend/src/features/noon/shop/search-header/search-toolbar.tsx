import { getTranslations } from "next-intl/server";

import MobileFiltersSheet from "../filter/mobile-view";
import SortSelect from "./sort-select";
import { Facets } from "../types";

type Props = {
  categoryName: string;
  resultsCount: number;
  facets?: Facets | null;
  hasFilters?: boolean;
  locale: string;
};

const ShopToolbar = async ({
  categoryName,
  resultsCount,
  facets,
  hasFilters,
  locale,
}: Props) => {
  const t = await getTranslations("shop");

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border-color pt-8 pb-2 mb-8">
      <h1 className="text-sm font-medium lg:text-base">
        {resultsCount.toLocaleString()}+ {t("resultsFor")} &quot;{categoryName}
        &quot;
      </h1>
      <div className="flex items-center gap-2">
        {hasFilters && <MobileFiltersSheet facets={facets} locale={locale} />}
        <SortSelect />
      </div>
    </div>
  );
};

export default ShopToolbar;
