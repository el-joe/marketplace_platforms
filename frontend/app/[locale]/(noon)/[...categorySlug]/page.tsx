import { formatCategoryName } from "@/src/utils/formatCategoryName";
import { fetchInstance } from "@/src/lib/utils";
import Shop from "@/src/features/noon/shop";
import { Product } from "@/types/globals";
import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { ShopResponse } from "@/src/features/noon/shop/types";
import FilterSidebar from "@/src/features/noon/shop/filter/desktop-view";
import getLocale from "@/src/helpers/getLocale";
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
  const locale = await getLocale();
  const isSearch = categorySlug?.[0] === "search" || !!sp.q;
  // The catch-all route's first segment is a bare slug (no scheme/host/query) —
  // the backend resolves it against the polymorphic `slugs` table, so the exact
  // same value works whether it points at a real category or a custom page
  // (an aggregate landing page spanning several categories, e.g. noon-deals-ae).
  const slug = categorySlug?.[0];

  let pageBuilderData: PageBuilder | null = null;
  let facets = null;
  let products: Product[] = [];
  let totalPages = TOTAL_PAGES;
  let totalCount = 0;
  let hasFilters = false;
  let categoryName =
    isSearch && sp.q ? sp.q : formatCategoryName(categorySlug);

  try {
    const queryParams = new URLSearchParams();
    if (slug && !isSearch) queryParams.set("category", slug);
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

    // Prefer the resolved entity's own localized name (works identically for a
    // category or a custom page — the API returns the same shape either way)
    // over the slug-guessed fallback set above.
    if (!isSearch && res.data?.category?.name) {
      categoryName = res.data.category.name[locale] || categoryName;
    }
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
        />
      </div>
    </main>
  );
}
