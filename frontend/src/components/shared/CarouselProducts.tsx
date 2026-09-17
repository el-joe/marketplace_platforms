"use client";
import SectionTitle from "./page-builder/sections/section-title";
import { Swiper, SwiperSlide } from "swiper/react";
// import { products } from "@/public/dummyData";
import ProductCard from "@/src/components/shared/product-card";
import { Navigation } from "swiper/modules";
import { useQuery } from "@tanstack/react-query";
import { fetchInstance } from "@/src/lib/utils";
import { IProduct } from "@/types";
import { getListingImage } from "@/src/types/media";

type props = {
  title: string;
  showViewAllButton?: boolean;
  // When provided, the carousel renders these products directly instead of
  // fetching its own data (e.g. sections already included in a page payload).
  items?: IProduct[];
};

const CarouselProducts = ({ title, showViewAllButton, items: providedItems }: props) => {
  const { data } = useQuery({
    queryKey: ["products"],
    queryFn: async () => {
      try {
        const { data } = await fetchInstance<{ data: { items: IProduct[] } }>(
          "/products",
        );
        return data ?? { items: [] };
      } catch {
        return { items: [] };
      }
    },
    enabled: providedItems === undefined,
  });
  const items = (providedItems ?? data?.items ?? []).filter(
    (product) =>
      product.slug &&
      product.product_url &&
      product.price > 0 &&
      !!getListingImage(product),
  );

  if (items.length === 0) return null;

  return (
    <div className="container py-6">
      <SectionTitle title={title} showVewAllButton={showViewAllButton} />
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={"auto"}
        spaceBetween={8}
        breakpoints={{
          768: {
            spaceBetween: 12,
          },
          1024: {
            spaceBetween: 18,
          },
        }}
      >
        {items.map((product) => (
          <SwiperSlide key={product.listing_id} className="w-fit! h-auto!">
            <ProductCard productData={product} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default CarouselProducts;
