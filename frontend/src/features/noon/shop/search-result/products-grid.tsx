import { Product } from "@/types/globals";
import ProductCard from "@/src/components/shared/product-card";

type Props = {
  products: Product[];
};

const ProductsGrid = ({ products }: Props) => {
  return (
    <div className="flex flex-wrap gap-3 lg:gap-4">
      {products.map((product, index) => (
        <ProductCard
          key={`${product.listing_id}-${index}`}
          productData={product}
        />
      ))}
    </div>
  );
};

export default ProductsGrid;
