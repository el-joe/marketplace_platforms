"use client";
import React from "react";
import { Block } from "./types";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import useLocale from "@/src/hooks/use-locale";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { AdBadge } from "@/src/components/shared/ad-badge";

type Props = {
  data: Block;
};

export default function HeroSlider({ data }: Props) {
  const locale = useLocale();
  return (
    <Swiper
      modules={[Navigation, Pagination, Autoplay]}
      navigation={!!data?.config?.show_arrows}
      pagination={
        !!data?.config?.show_dots && {
          bulletClass:
            "w-[5px] h-[5px] md:w-6! md:h-1.5! rounded-full inline-block bg-border-color me-1 cursor-pointer",
          bulletActiveClass: "bg-gray! md:bg-main! w-2! h-2! md:w-6 md:h-1.5",
          clickableClass: "w-fit! left-1/2! -translate-x-1/2",
          clickable: true,
        }
      }
      loop={!!data?.config?.loop}
      // autoplay={{
      //   delay: +(data?.config?.autoplay_seconds || 3) * 1000,
      //   pauseOnMouseEnter: true,
      // }}
      speed={1500}
      className="flex-1 pb-6! md:pb-0! h-full max-h-100"
      spaceBetween={20}
      slidesPerView={1.2}
      breakpoints={{
        768: {
          spaceBetween: 0,
          slidesPerView: 1,
        },
      }}
    >
      {data?.slides?.map((banner) => (
        <SwiperSlide key={banner.title[locale]} className="h-auto! max-h-100">
          <Link href={banner?.cta_url || "#"} className="block relative h-full">
            <Image
              src={banner?.desktop_url || banner?.mobile_url}
              alt="banner"
              width={2400}
              height={400}
              quality={100}
              className="rounded-4xl md:rounded-none h-full "
            />
            {banner?.is_paid && <AdBadge />}
          </Link>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
