"use client";
import { useCartContext } from "@/src/providers/cart-provider";
import Image from "next/image";
import React from "react";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

// const slides = [
//   {
//     id: 1,
//     slide:
//       "https://a.nooncdn.com/mpcms/EN0001/assets/c2c1a7ca-f536-4842-b15c-bbb587f107bc.png?width=2400",
//   },
//   {
//     id: 2,
//     slide:
//       "https://a.nooncdn.com/mpcms/EN0001/assets/3f84af24-52f9-44e2-94e8-d4bbda87479c.png?width=2400",
//   },
// ];

export default function TopBannerSlides() {
  const { cart } = useCartContext();
  return (
    <Swiper
      modules={[Navigation]}
      className="lg:rounded-full mb-4"
      loop
      navigation
    >
      {/* {cart?.cart_banner.map((slide) => ( */}
      <SwiperSlide>
        <Image
          src={cart?.cart_banner?.desktop_image_url as string}
          alt="slide"
          width={1280}
          height={80}
          className="h-16 lg:h-14 max-w-full object-fill rounded-full "
        />
      </SwiperSlide>
      {/* ))} */}
    </Swiper>
  );
}
