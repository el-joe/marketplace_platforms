import ProductsGrid from "@/src/features/noon/shop/search-result/products-grid";
import Pagination from "@/src/components/shared/pagination";
import ShopToolbar from "@/src/features/noon/shop/search-header/search-toolbar";
import { Product } from "@/types/globals";
import { DynamicLayout } from "@/src/components/shared/page-builder";
import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { PlacementBanner } from "@/src/components/shared/placement-banner";
import { PlacementBanner as PlacementBannerType } from "@/src/types/placement-banner";
import { Facets } from "@/src/features/noon/shop/types";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";

interface Props {
  pageBuilderData: PageBuilder | null;
  categoryName: string;
  products: Product[];
  totalPages: number;
  totalCount: number;
  topBanner?: PlacementBannerType | null;
  isSearch?: boolean;
  facets?: Facets | null;
  hasFilters?: boolean;
  locale: string;
}

export default async function Shop({
  totalPages,
  products,
  pageBuilderData,
  categoryName,
  totalCount,
  topBanner,
  isSearch,
  facets,
  hasFilters,
  locale,
}: Props) {
  const t = await getTranslations("shop");
  return (
    <>
      {pageBuilderData?.sections.map((e) => (
        <DynamicLayout key={e.id} section={e} />
      ))}

      {topBanner && (
        <div className="my-4">
          <PlacementBanner
            banner={topBanner}
            variant={isSearch ? "search" : "category"}
          />
        </div>
      )}

      <ShopToolbar
        categoryName={categoryName}
        resultsCount={totalCount}
        facets={facets}
        hasFilters={hasFilters}
        locale={locale}
      />

      <div>
        {products.length > 0 ? (
          <ProductsGrid products={products} />
        ) : (
          <div className="flex flex-col items-center gap-3 py-24 text-center">
            <p className="text-lg font-semibold text-primary">
              {t("noProductsFound")}
            </p>
            <p className="text-sm text-gray">{t("tryAdjustingFilters")}</p>
          </div>
        )}
      </div>
      {totalPages > 1 && <Pagination totalPages={totalPages} />}

      <div className="my-8 flex flex-col items-center gap-2 text-center">
        <p className="text-sm text-gray">{t("cantFindIt")}</p>
        <Link
          href="/special-requests/create"
          className="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white"
        >
          {t("sendSpecialRequest")}
        </Link>
      </div>
    </>
  );
}
