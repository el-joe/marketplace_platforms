import ProductsGrid from "@/src/features/noon/shop/search-result/products-grid";
import Pagination from "@/src/components/shared/pagination";
import ShopToolbar from "@/src/features/noon/shop/search-header/search-toolbar";
import { Product } from "@/types/globals";
import { DynamicLayout } from "@/src/components/shared/page-builder";
import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { PlacementBanner } from "@/src/components/shared/placement-banner";
import { PlacementBanner as PlacementBannerType } from "@/src/types/placement-banner";
import { Facets } from "@/src/features/noon/shop/types";

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
              No products found
            </p>
            <p className="text-sm text-gray">Try adjusting your filters</p>
          </div>
        )}
      </div>
      {totalPages > 1 && <Pagination totalPages={totalPages} />}
    </>
  );
}
