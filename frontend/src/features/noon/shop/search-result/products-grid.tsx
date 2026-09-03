import ProductCard from "@/src/components/shared/product-card";
import { Product } from "@/types/globals";

type Props = {
  products: Product[];
  hasFilters?: boolean;
};

const ProductsGrid = ({ products, hasFilters = false }: Props) => {
  return (
    <div
      className={
        hasFilters
          ? "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 lg:gap-4"
          : "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 lg:gap-4"
      }
    >
      {products.map((product, index) => (
        <ProductCard
          key={`${product.listing_id}-${index}`}
          productData={product}
          compact={!hasFilters}
        />
      ))}
    </div>
  );
};

export default ProductsGrid;
