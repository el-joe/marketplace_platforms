import { IProduct } from "@/types";
import ProductCard from "@/src/components/shared/ProductCard";

type Props = {
  products: IProduct[];
};

const ProductsGrid = ({ products }: Props) => {
  return (
    <div className="flex flex-wrap gap-3 lg:gap-4">
      {products.map((product, index) => (
        <ProductCard key={`${product.listing_id}-${index}`} productData={product} />
      ))}
    </div>
  );
};

export default ProductsGrid;
