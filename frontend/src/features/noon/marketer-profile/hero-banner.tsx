"use client";
import Image from "next/image";
import React from "react";
import { Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Banners } from "./helpers/types";

export default function HeroBanner({ banners }: { banners: Banners[] }) {
  return (
    <Swiper
      modules={[Navigation, Pagination]}
      navigation={banners.length > 1}
      pagination={banners.length > 1 ? { clickable: true } : false}
      className="mb-4"
      loop={banners.length > 1}
      slidesPerView={1}
    >
      {banners.map((b) => (
        <SwiperSlide key={b.id}>
          <picture>
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
              className="max-h-112"
            />
          </picture>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
