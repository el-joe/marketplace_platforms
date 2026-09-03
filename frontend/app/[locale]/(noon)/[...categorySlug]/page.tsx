import { formatCategoryName } from "@/src/utils/formatCategoryName";
import { fetchInstance } from "@/src/lib/utils";
import Shop from "@/src/features/noon/shop";
import { Product } from "@/types/globals";
import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { ShopResponse } from "@/src/features/noon/shop/types";
import FilterSidebar from "@/src/features/noon/shop/filter/desktop-view";
import {
  FILTER_PREFIX,
  resolveApiFilters,
} from "@/src/helpers/resolveApiFilters";

const TOTAL_PAGES = 5;

type Props = {
  params: Promise<{ categorySlug: string[] }>;
  searchParams: Promise<Record<string, string>>;
};

export default async function ShopPage({ params, searchParams }: Props) {
  const { categorySlug } = await params;
  const sp = await searchParams;
  const isSearch = categorySlug?.[0] === "search" || !!sp.q;
  const categoryName =
    isSearch && sp.q ? sp.q : formatCategoryName(categorySlug);

  let pageBuilderData: PageBuilder | null = null;
  let facets = null;
  let products: Product[] = [];
  let totalPages = TOTAL_PAGES;
  let totalCount = 0;
  let hasFilters = false;

  try {
    const queryParams = new URLSearchParams();
    if (categorySlug && !isSearch)
      queryParams.set("category", categorySlug?.[0]);
    Object.entries(sp).forEach(([key, value]) => {
      if (value && !key.startsWith(`${FILTER_PREFIX}_`)) {
        queryParams.set(key, value);
      }
    });

    const endpoint = isSearch ? "/search" : "/products";
    const urlSegments = endpoint.split("/").filter(Boolean);
    const apiFilters = await resolveApiFilters();
    const endpointFilter = apiFilters.find((filter) =>
      urlSegments.includes(filter.targetEndpoint),
    );
    if (endpointFilter) {
      Object.entries(endpointFilter.filters).forEach(([key, value]) => {
        queryParams.set(key, value);
      });
    }

    const query = queryParams.toString();

    const res = await fetchInstance<ShopResponse>(
      `${endpoint}${query ? `?${query}` : ""}`,
    );

    products = res.data?.items ?? [];
    pageBuilderData = res?.data?.page_builder;
    facets = res.data?.facets;
    totalPages = res.data?.meta?.last_page ?? TOTAL_PAGES;
    totalCount = res.data?.meta?.total ?? products.length;
    hasFilters = isSearch ? true : (res.data?.category?.has_filters ?? false);
  } catch {
    products = [];
    totalCount = 0;
    totalPages = 1;
  }

  return (
    <main className="container flex flex-col gap-4 py-4 lg:flex-row lg:gap-6">
      {hasFilters && (
        <aside className="w-[250px] h-[calc(100vh-100px)] sticky top-[100px] overflow-y-auto scrollbar-hide shrink-0">
          <FilterSidebar facets={facets as ShopResponse["data"]["facets"]} />
        </aside>
      )}
      <div className={hasFilters ? "min-w-0 flex-1" : "w-full"}>
        <Shop
          pageBuilderData={pageBuilderData}
          categoryName={categoryName}
          products={products}
          totalPages={totalPages}
          totalCount={totalCount}
          hasFilters={hasFilters}
        />
      </div>
    </main>
  );
}
