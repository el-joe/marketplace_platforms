"use client";
import { useQuery } from "@tanstack/react-query";
import { Skeleton } from "@/src/components/ui/skeleton";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";
import ProductCard from "@/src/components/shared/ProductCard";
import { getRecommendationsService } from "./api/get";
import useLocale from "@/src/hooks/use-locale";

export default function RecommendedSection() {
  const locale = useLocale();
  const { data, isLoading } = useQuery({
    queryKey: ["recommended"],
    queryFn: getRecommendationsService,
  });
  if (isLoading) {
    return <Skeleton className="h-20" />;
  }
  return (
    <div className="rounded-[16px] bg-white max-w-110 lg:max-w-145 xl:max-w-180 hidden md:block px-4 pb-4">
      <h2 className="text-light flex-1 font-bold text-lg md:text-xl xl:text-2xl my-4">
        {locale === "ar"
          ? data?.data?.sections[0]?.title_ar
          : data?.data?.sections[0]?.title_en}
      </h2>
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
        {(data?.data?.sections[0]?.listings ?? []).map((product) => (
          <SwiperSlide key={product?.listing_id} className="w-fit! h-auto!">
            <ProductCard productData={product} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
}
