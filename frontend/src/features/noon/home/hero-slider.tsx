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

  if (data?.config?.is_announcement) {
    const slide = data?.slides?.[0];
    if (!slide) return null;

    const imageUrl = slide.desktop_url || slide.mobile_url;
    if (!imageUrl) return null;

    const inner = (
      <div className="relative w-full h-12 md:h-14">
        <Image
          src={imageUrl}
          alt="Announcement"
          fill
          className="object-cover object-center"
          sizes="100vw"
          priority
        />
      </div>
    );

    return (
      <div className="w-full">
        {slide.cta_url ? (
          <Link href={slide.cta_url} className="block w-full">
            {inner}
          </Link>
        ) : (
          inner
        )}
      </div>
    );
  }

  return (
    <Swiper
      modules={[Navigation, Pagination, Autoplay]}
      style={{ "--swiper-pagination-bottom": "0" } as React.CSSProperties}
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
      autoplay={{ delay: Number(data?.config?.autoplay_seconds) * 1000 }}
      loop={!!data?.config?.loop}
      speed={1500}
      className="flex-1 apb-6! md:pb-0! h-full max-h-100 swiper-hidden-navigation"
      spaceBetween={20}
      slidesPerView={1.2}
      breakpoints={{ 768: { spaceBetween: 0, slidesPerView: 1 } }}
    >
      {data?.slides?.map((banner) => (
        <SwiperSlide key={banner.title[locale]} className="h-auto! max-h-100">
          <Link
            href={banner?.cta_url || "#"}
            className="block relative h-full"
            target={banner.cta_open_new_tab ? "_blank" : "_self"}
          >
            <picture>
              <source
                media="(min-width: 768px)"
                srcSet={banner?.desktop_url || ""}
              />
              <source
                media="(max-width: 767px)"
                srcSet={banner?.mobile_url || ""}
              />
              <Image
                src={
                  data.banner?.image_url || data.banner?.mobile_image_url || ""
                }
                alt={banner?.title[locale] || ""}
                className={"object-cover responsive-ratio h-full max-h-100"}
                // style={
                //   {
                //     "--banner-ratio": aspectRatio,
                //     "--banner-mobile-ratio": mobileAspectRatio,
                //   } as React.CSSProperties
                // }
                width={2400}
                height={400}
              />
            </picture>
            {banner?.is_paid && <AdBadge />}
          </Link>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
