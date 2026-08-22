"use client";
import { Swiper, SwiperSlide } from "swiper/react";
// import { products } from "@/public/dummyData";
import ProductCard from "@/src/components/shared/ProductCard";
import { Navigation } from "swiper/modules";
import { Block } from "./types";
import SectionTitle from "./section-title";
import useLocale from "@/src/hooks/use-locale";

export const ProductsSlider = ({ data }: { data: Block }) => {
  const locale = useLocale();
  return (
    <div className="container py-6">
      <SectionTitle
        title={
          locale === "ar"
            ? data.config?.title_ar || ""
            : data.config?.title_en || ""
        }
        showVewAllButton={!!data.config?.show_view_all || false}
      />
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
        {data?.products?.map((product) => (
          <SwiperSlide key={product.listing_id} className="w-fit! h-auto!">
            <ProductCard productData={product} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};
