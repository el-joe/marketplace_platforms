"use client";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";
import ProductCard from "@/src/components/shared/product-card";
import { useCartContext } from "@/src/providers/cart-provider";
import { useTranslations } from "next-intl";

export default function SuggestedProductsSection() {
  const { cart } = useCartContext();
  const t = useTranslations("cart");
  const suggestedProducts = cart?.suggested_products ?? [];

  if (!suggestedProducts.length) {
    return null;
  }

  return (
    <div className="rounded-[16px] bg-white max-w-110 lg:max-w-145 xl:max-w-180 hidden md:block px-4 pb-4">
      <h2 className="text-light flex-1 font-bold text-lg md:text-xl xl:text-2xl my-4">
        {t("suggestedProducts")}
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
        {suggestedProducts.map((product) => (
          <SwiperSlide key={product.listing_id} className="w-fit! h-auto!">
            <ProductCard productData={product} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
}
