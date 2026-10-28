"use client";
import Image from "next/image";
import React from "react";
import { Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Banners } from "./helpers/types";

export default function HeroBanner({ banners }: { banners: Banners[] }) {
  if (!banners.length) return null;

  return (
    <Swiper
      modules={[Navigation, Pagination]}
      navigation={banners.length > 1}
      pagination={banners.length > 1 ? { clickable: true } : false}
      className="mb-4 h-[300px]"
      loop={banners.length > 1}
      slidesPerView={1}
    >
      {banners.map((b) => (
        <SwiperSlide key={b.id} className="!h-full">
          <picture className="block h-full w-full">
            <source
              media="(min-width: 768px)"
              srcSet={b.image_desktop || "/images/no-image-available-icon.jpg"}
            />
            <source
              media="(max-width: 767px)"
              srcSet={b.image_mobile || "/images/no-image-available-icon.jpg"}
            />
            <Image
              src={
                b.image_desktop ||
                b.image_mobile ||
                "/images/no-image-available-icon.jpg"
              }
              alt={b.title}
              width={2440}
              height={860}
              className="h-full w-full object-cover"
            />
          </picture>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
