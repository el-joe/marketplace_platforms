"use client";
import Image from "next/image";
import Link from "next/link";
import React from "react";
import { EffectFade, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IMarketerBanner } from "./helpers/types";

const FALLBACK_IMAGE = "/images/no-image-available-icon.jpg";

export default function HeroBanner({
  banners,
}: {
  banners: IMarketerBanner[];
}) {
  const slides = banners.filter((b) => b.image_desktop || b.image_mobile);
  if (!slides.length) return null;

  return (
    <Swiper
      modules={[Navigation, EffectFade]}
      effect="fade"
      navigation={slides.length > 1}
      className="mb-4"
      loop={slides.length > 1}
      slidesPerView={1}
    >
      {slides.map((b) => {
        const desktop = b.image_desktop || b.image_mobile || FALLBACK_IMAGE;
        const mobile = b.image_mobile || desktop;
        const picture = (
          <picture>
            <source media="(min-width: 768px)" srcSet={desktop} />
            <source media="(max-width: 767px)" srcSet={mobile} />
            <Image
              src={desktop}
              alt={b.title ?? ""}
              width={2440}
              height={860}
            />
          </picture>
        );
        const href = b.link || b.click_url;
        return (
          <SwiperSlide key={b.id}>
            {href ? <Link href={href}>{picture}</Link> : picture}
          </SwiperSlide>
        );
      })}
    </Swiper>
  );
}
