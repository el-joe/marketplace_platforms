"use client";
import Image from "next/image";
import React from "react";
import { EffectFade, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

export default function HeroBanner() {
  return (
    <Swiper
      modules={[Navigation, EffectFade]}
      effect="fade"
      navigation
      className="mb-4"
      loop
      slidesPerView={1}
    >
      {[
        "https://images.boutiqaat.com/opticalshrink/v1/image/BF_2026___Top_Picks___Best_Deals___All_Countries_2606d5bc_5c45_4366_8f37_a2da889e94df.webp?resource=page1&type=slider",
        "https://images.boutiqaat.com/opticalshrink/v1/image/BF_2026___Top_Picks___Best_Deals___All_Countries_40a3f34f_e7e9_4465_9aee_dbf0d00ffffb.webp?resource=page1&type=slider",
      ].map((e) => (
        <SwiperSlide key={e}>
          <Image src={e} alt="" width={2440} height={860} />
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
