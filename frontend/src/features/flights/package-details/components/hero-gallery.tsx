"use client";

import { useRef } from "react";
import Image from "next/image";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, EffectFade } from "swiper/modules";
import { Swiper as SwiperType } from "swiper/types";
import { useTranslations } from "next-intl";
import "swiper/css/effect-fade";
import type { TravelPackageImage } from "../../helpers/types";

type Props = {
  images: TravelPackageImage[];
  destination: string;
  country: string;
  description: string;
};

export default function HeroGallery({
  images,
  destination,
  country,
  description,
}: Props) {
  const t = useTranslations("flights.packageDetails");
  const swiperRef = useRef<SwiperType | null>(null);

  const sortedImages = [...images].sort((a, b) => a.position - b.position);

  return (
    <section className="relative w-full h-[70vh] min-h-[520px] overflow-hidden">
      <div className="absolute inset-0 h-full w-full">
        <Swiper
          modules={[Autoplay, EffectFade]}
          effect="fade"
          fadeEffect={{ crossFade: true }}
          autoplay={{ delay: 4500, disableOnInteraction: false }}
          loop
          onSwiper={(swiper) => {
            swiperRef.current = swiper;
          }}
          className="h-full w-full"
        >
          {sortedImages.map((image, i) => (
            <SwiperSlide key={image.id}>
              <div className="relative w-full h-full">
                <Image
                  src={image.url || "/images/no-image-available-icon.jpg"}
                  alt={`${destination} ${i + 1}`}
                  fill
                  priority={i === 0}
                  sizes="100vw"
                  className="object-cover"
                />
                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/15 to-black/10" />
              </div>
            </SwiperSlide>
          ))}
        </Swiper>
      </div>

      {/* Content overlay */}
      <div className="relative z-10 h-full flex flex-col justify-end pb-16 sm:pb-20 px-4 sm:px-8 max-w-[1200px] mx-auto">
        <div className="max-w-2xl">
          <div className="inline-flex items-center gap-2 px-3 py-1 bg-main rounded-full mb-6 w-fit">
            <span className="w-2 h-2 rounded-full bg-primary animate-pulse" />
            <span className="text-primary text-xs font-bold uppercase tracking-widest">
              {t("premiumBadge")}
            </span>
          </div>
          <h1 className="text-5xl sm:text-6xl lg:text-7xl leading-tight font-extrabold tracking-tight text-white mb-4">
            {destination} <span className="text-main">{country}</span>
          </h1>
          <p className="text-base sm:text-lg text-white/80 max-w-lg leading-relaxed line-clamp-3">
            {description}
          </p>
        </div>
      </div>

      {/* Nav arrows */}
      <div className="absolute bottom-8 sm:bottom-10 inset-e-4 sm:inset-e-8 z-20 flex gap-3">
        <button
          type="button"
          aria-label={t("previousSlide")}
          onClick={() => swiperRef.current?.slidePrev()}
          className="w-11 h-11 sm:w-12 sm:h-12 rounded-full border border-white/20 flex items-center justify-center text-white hover:bg-white/10 transition-colors cursor-pointer"
        >
          <ChevronLeftIcon className="size-5" />
        </button>
        <button
          type="button"
          aria-label={t("nextSlide")}
          onClick={() => swiperRef.current?.slideNext()}
          className="w-11 h-11 sm:w-12 sm:h-12 rounded-full border border-white/20 flex items-center justify-center text-white hover:bg-white/10 transition-colors cursor-pointer"
        >
          <ChevronRightIcon className="size-5" />
        </button>
      </div>
    </section>
  );
}
