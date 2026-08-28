"use client";
import SectionTitle from "../../features/noon/home/section-title";
import { Swiper, SwiperSlide } from "swiper/react";
// import { products } from "@/public/dummyData";
import ProductCard from "@/src/components/shared/ProductCard";
import { Navigation } from "swiper/modules";
import { useQuery } from "@tanstack/react-query";
import { fetchInstance } from "@/src/lib/utils";
import { IProduct } from "@/types";

type props = {
  title: string;
  showViewAllButton?: boolean;
};

const CarouselProducts = ({ title, showViewAllButton }: props) => {
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
  });
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
        {(data?.items ?? []).map((product) => (
          <SwiperSlide key={product.listing_id} className="w-fit! h-auto!">
            <ProductCard productData={product} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default CarouselProducts;
