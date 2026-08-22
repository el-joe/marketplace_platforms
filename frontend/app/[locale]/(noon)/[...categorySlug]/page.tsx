import ProductsGrid from "@/src/features/noon/shop/search-result/products-grid";
import Pagination from "@/src/components/shared/pagination";
import ShopToolbar from "@/src/features/noon/shop/search-header/search-toolbar";
import { formatCategoryName } from "@/src/utils/formatCategoryName";
import { fetchInstance } from "@/src/lib/utils";
import type { IProduct } from "@/types";

const TOTAL_PAGES = 5;

type Props = {
  params: Promise<{ categorySlug: string[] }>;
  searchParams: Promise<Record<string, string>>;
};

type ProductsResponse = {
  data: {
    items: IProduct[];
    meta: {
      current_page: number;
      last_page: number;
      total: number;
    };
  };
};

export default async function ShopPage({ params, searchParams }: Props) {
  const { categorySlug } = await params;
  const sp = await searchParams;
  const categoryName = formatCategoryName(categorySlug);

  let products: IProduct[] = [];
  let totalPages = TOTAL_PAGES;
  let totalCount = 0;

  try {
    const queryParams = new URLSearchParams();
    if (sp.page) queryParams.set("page", sp.page);
    if (sp.sort) queryParams.set("sort", sp.sort);
    if (sp.min_price) queryParams.set("price_min", sp.min_price);
    if (sp.max_price) queryParams.set("price_max", sp.max_price);

    const query = queryParams.toString();
    const res = await fetchInstance<ProductsResponse>(
      `/products${query ? `?${query}` : ""}`,
    );
    products = res.data?.items ?? [];
    totalPages = res.data?.meta?.last_page ?? TOTAL_PAGES;
    totalCount = res.data?.meta?.total ?? products.length;
  } catch {
    products = [];
    totalCount = 0;
    totalPages = 1;
  }

  return (
    <>
      <ShopToolbar categoryName={categoryName} resultsCount={totalCount} />
      <div className="pt-4">
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
      <Pagination totalPages={totalPages} />
    </>
  );
}
