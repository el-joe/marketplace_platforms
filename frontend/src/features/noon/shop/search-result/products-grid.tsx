import ProductCard from "@/src/components/shared/product-card";
import { Product } from "@/types/globals";

type Props = {
  products: Product[];
};

const ProductsGrid = ({ products }: Props) => {
  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 lg:gap-4">
      {products.map((product, index) => (
        <div key={`${product.listing_id}-${index}`} className="[&>div]:w-full">
          <ProductCard productData={product} />
        </div>
      ))}
    </div>
  );
};

export default ProductsGrid;
