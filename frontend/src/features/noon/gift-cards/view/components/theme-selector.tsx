"use client";

import Image from "next/image";
import { useLocale, useTranslations } from "next-intl";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Pagination } from "swiper/modules";
import { cn } from "@/src/lib/utils";

type Props = {
  images: string[];
  selectedIndex: number;
  onSelect: (index: number) => void;
};

export default function ThemeSelector({ images, selectedIndex, onSelect }: Props) {
  const t = useTranslations("giftCards");
  const locale = useLocale();

  return (
    <div>
      <p className="font-medium mb-2">{t("selectATheme")}</p>
      <Swiper
        modules={[Navigation, Pagination]}
        navigation={{
          nextEl: ".gift-card-theme-next",
          prevEl: ".gift-card-theme-prev",
        }}
        pagination={{ clickable: true }}
        dir={locale === "ar" ? "rtl" : "ltr"}
        spaceBetween={12}
        slidesPerView={3.5}
        className="relative pb-6"
        style={{ "--swiper-pagination-bullet-size": "6px" } as React.CSSProperties}
      >
        {images.map((image, index) => (
          <SwiperSlide key={image + index}>
            <button
              type="button"
              onClick={() => onSelect(index)}
              className={cn(
                "block w-full aspect-600/370 p-2 rounded-md border cursor-pointer",
                selectedIndex === index ? "border-blue-3" : "border-transparent",
              )}
            >
              <div className="relative w-full h-full overflow-hidden rounded-sm">
                <Image src={image} alt="" fill sizes="102px" className="object-cover" />
              </div>
            </button>
          </SwiperSlide>
        ))}
        <button
          type="button"
          aria-label="next"
          className="gift-card-theme-next absolute -right-4 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white shadow p-1"
        >
          {locale === "ar" ? (
            <ChevronLeftIcon className="size-4" />
          ) : (
            <ChevronRightIcon className="size-4" />
          )}
        </button>
      </Swiper>
    </div>
  );
}
