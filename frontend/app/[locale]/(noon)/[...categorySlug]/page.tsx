import { formatCategoryName } from "@/src/utils/formatCategoryName";
import { fetchInstance } from "@/src/lib/utils";
import Shop from "@/src/features/noon/shop";
import { Product } from "@/types/globals";
import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { ShopResponse } from "@/src/features/noon/shop/types";
import FilterSidebar from "@/src/features/noon/shop/filter/desktop-view";

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

  try {
    const queryParams = new URLSearchParams();
    if (categorySlug) queryParams.set("category", categorySlug?.[0]);
    if (sp.page) queryParams.set("page", sp.page);
    if (sp.sort) queryParams.set("sort", sp.sort);
    if (sp.min_price) queryParams.set("price_min", sp.min_price);
    if (sp.max_price) queryParams.set("price_max", sp.max_price);
    if (sp.q) queryParams.set("q", sp.q);

    const endpoint = isSearch ? "/search" : "/products";
    const query = queryParams.toString();
    const res = await fetchInstance<ShopResponse>(
      `${endpoint}${query ? `?${query}` : ""}`,
    );

    pageBuilderData = res?.data?.page_builder;
    facets = res.data?.facets;
    products = res.data?.items ?? [];
    totalPages = res.data?.meta?.last_page ?? TOTAL_PAGES;
    totalCount = res.data?.meta?.total ?? products.length;
  } catch {
    products = [];
    totalCount = 0;
    totalPages = 1;
  }

  return (
    <main className="container flex flex-col gap-4 py-4 lg:flex-row lg:gap-6">
      <aside className="w-[250px] h-[calc(100vh-100px)] sticky top-[100px] overflow-y-auto scrollbar-hide">
        <FilterSidebar />
      </aside>
      <div className="min-w-0 flex-1">
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
