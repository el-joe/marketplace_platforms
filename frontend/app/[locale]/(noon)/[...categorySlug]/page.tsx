import { products as dummyProducts } from "@/public/dummyData";
import ProductsGrid from "@/src/features/noon/shop/search-result/products-grid";
import Pagination from "@/src/components/shared/pagination";
import ShopToolbar from "@/src/features/noon/shop/search-header/search-toolbar";
import { formatCategoryName } from "@/src/utils/formatCategoryName";

const PAGE_REPEAT_COUNT = 3;
const TOTAL_PAGES = 5;

type Props = {
  params: Promise<{ categorySlug: string[] }>;
};

export default async function ShopPage({ params }: Props) {
  const { categorySlug } = await params;
  const categoryName = formatCategoryName(categorySlug);

  const products = Array.from({ length: PAGE_REPEAT_COUNT }, (_, multiplier) =>
    dummyProducts.map((product) => ({
      ...product,
      id: product.id + multiplier * 100,
    })),
  ).flat();

  return (
    <>
      <ShopToolbar categoryName={categoryName} resultsCount={products.length} />
      <div className="pt-4">
        <ProductsGrid products={products} />
      </div>
      <Pagination totalPages={TOTAL_PAGES} />
    </>
  );
}
