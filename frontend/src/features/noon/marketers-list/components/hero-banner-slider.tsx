"use client";

import { useState } from "react";
import type { Swiper as SwiperType } from "swiper";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, A11y } from "swiper/modules";
import { ChevronLeft, ChevronRight } from "lucide-react";
import useLocale from "@/src/hooks/use-locale";
import type { MarketerBanner } from "../helpers/types";
import BannerLink from "./banner-link";
import BannerImage from "./banner-image";

interface Props {
  banners: MarketerBanner[];
  regionLabel: string;
  prevLabel: string;
  nextLabel: string;
  /** Template with `{n}` placeholder, e.g. "Go to slide {n}". */
  goToLabel: string;
}

const arrowClass =
  "absolute top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-primary shadow-md transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary md:flex";

export default function HeroBannerSlider({ banners, regionLabel, prevLabel, nextLabel, goToLabel }: Props) {
  const locale = useLocale();
  const dir = locale === "ar" ? "rtl" : "ltr";
  const [swiper, setSwiper] = useState<SwiperType | null>(null);
  const [active, setActive] = useState(0);
  const multiple = banners.length > 1;

  return (
    <section
      aria-roledescription="carousel"
      aria-label={regionLabel}
      className="relative overflow-hidden rounded-2xl"
    >
      <Swiper
        dir={dir}
        key={dir}
        modules={[Autoplay, A11y]}
        onSwiper={setSwiper}
        onSlideChange={(s) => setActive(s.realIndex)}
        loop={multiple}
        autoplay={multiple ? { delay: 5000, disableOnInteraction: false, pauseOnMouseEnter: true } : false}
        speed={700}
        allowTouchMove={multiple}
        slidesPerView={1}
      >
        {banners.map((banner, i) => (
          <SwiperSlide key={banner.id}>
            <BannerLink
              href={banner.link}
              clickUrl={banner.click_url}
              ariaLabel={banner.title ?? undefined}
              className="relative block aspect-[16/9] w-full md:aspect-[1700/450]"
            >
              <BannerImage banner={banner} sizes="100vw" priority={i === 0} />
            </BannerLink>
          </SwiperSlide>
        ))}
      </Swiper>

      {multiple && (
        <>
          <button
            type="button"
            aria-label={prevLabel}
            onClick={() => swiper?.slidePrev()}
            className={`${arrowClass} ltr:left-3 rtl:right-3`}
          >
            <ChevronLeft className="h-5 w-5 rtl:rotate-180" />
          </button>
          <button
            type="button"
            aria-label={nextLabel}
            onClick={() => swiper?.slideNext()}
            className={`${arrowClass} ltr:right-3 rtl:left-3`}
          >
            <ChevronRight className="h-5 w-5 rtl:rotate-180" />
          </button>
          <div className="absolute inset-x-0 bottom-3 z-10 flex justify-center gap-2">
            {banners.map((banner, i) => (
              <button
                key={banner.id}
                type="button"
                aria-label={goToLabel.replace("{n}", String(i + 1))}
                aria-current={i === active}
                onClick={() => swiper?.slideToLoop(i)}
                className={`h-2 rounded-full transition-all ${i === active ? "w-6 bg-white" : "w-2 bg-white/60"}`}
              />
            ))}
          </div>
        </>
      )}
    </section>
  );
}
