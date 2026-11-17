"use client";
import React from "react";
import { Block } from "../types";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation } from "swiper/modules";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { SponsoredLink } from "@/src/components/shared/sponsored-link";
import { useSlideRatio } from "../helpers/use-slide-ratio";
import { useHeroSlider } from "../helpers/use-hero-slider";
import { cn } from "@/src/lib/utils";

type Props = {
  data: Block;
};

export default function HeroSlider({ data }: Props) {
  const locale = useLocale();
  const t = useTranslations("pageBuilder");
  const { ratio, onFirstSlideLoad } = useSlideRatio();
  const { renderedSlides, activeIndex, onSwiper, onSlideChange, goTo } =
    useHeroSlider(data?.slides ?? [], !!data?.config?.loop);
  const realSlidesCount = data?.slides?.length ?? 0;

  if (data?.config?.is_announcement) {
    const slide = data?.slides?.[0];
    if (!slide) return null;

    const imageUrl =
      slide.desktop_url?.[locale] ||
      slide.desktop_url?.en ||
      slide.mobile_url?.[locale] ||
      slide.mobile_url?.en;
    if (!imageUrl) return null;

    const inner = (
      <div className="relative w-full h-12 md:h-14">
        <Image
          src={imageUrl || "/images/no-image-available-icon.jpg"}
          alt={t("announcement")}
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
    <div className="relative flex-1 h-full">
      <Swiper
        modules={[Navigation, Autoplay]}
        style={{ "--slide-ratio": ratio } as React.CSSProperties}
        navigation={!!data?.config?.show_arrows}
        autoplay={{ delay: Number(data?.config?.autoplay_seconds) * 1000 }}
        loop={!!data?.config?.loop && realSlidesCount > 1}
        speed={800}
        onSwiper={onSwiper}
        onSlideChange={onSlideChange}
        // Mobile: noon-style card carousel — the active banner is centered with
        // the neighbours peeking on both sides. Desktop keeps the full-width banner.
        className="pt-3! pb-2! md:py-0! h-full md:max-h-100 swiper-hidden-navigation"
        centeredSlides
        spaceBetween={12}
        slidesPerView={1.22}
        breakpoints={{
          768: { spaceBetween: 0, slidesPerView: 1, centeredSlides: false },
        }}
      >
      {renderedSlides.map(({ slide: banner, index, copy }) => {
        const desktopUrl =
          banner?.desktop_url?.[locale] || banner?.desktop_url?.en;
        const mobileUrl =
          banner?.mobile_url?.[locale] || banner?.mobile_url?.en;
        const alt = banner?.title?.[locale] || banner?.title?.en || "";
        return (
          <SwiperSlide key={`${banner.id}-${copy}`} className="h-auto! md:max-h-100">
            <SponsoredLink
              href={banner?.cta_url || "#"}
              isExternal={banner.is_external}
              ad={banner.ad}
              isPaid={banner.is_paid}
              className="h-full overflow-hidden rounded-[18px] bg-gray-2 shadow-[0_6px_18px_rgba(31,35,51,0.12)] aspect-(--slide-ratio) md:aspect-auto md:rounded-none md:bg-transparent md:shadow-none md:overflow-visible"
            >
              {/* Mobile only: a blurred copy fills the card behind the banner, so a
                  banner whose shape differs from the card is never stretched or cropped. */}
              <Image
                src={mobileUrl || desktopUrl || "/images/no-image-available-icon.jpg"}
                alt=""
                aria-hidden
                fill
                sizes="40px"
                className="object-cover scale-110 blur-xl opacity-70 md:hidden"
              />
              <picture>
                <source
                  media="(min-width: 768px)"
                  srcSet={
                    desktopUrl ||
                    mobileUrl ||
                    "/images/no-image-available-icon.jpg"
                  }
                />
                <source
                  media="(max-width: 767px)"
                  srcSet={
                    mobileUrl ||
                    desktopUrl ||
                    "/images/no-image-available-icon.jpg"
                  }
                />
                <Image
                  src={
                    desktopUrl ||
                    mobileUrl ||
                    "/images/no-image-available-icon.jpg"
                  }
                  alt={alt}
                  onLoad={index === 0 && copy === 0 ? onFirstSlideLoad : undefined}
                  priority={index === 0 && copy === 0}
                  // Mobile: contain inside the rounded card. Desktop: fill (not
                  // cover) so the whole banner — headline and CTA — stays visible
                  // instead of being cropped to the 400px box.
                  className="absolute inset-0 w-full h-full object-contain md:static md:max-h-100 md:object-fill"
                  width={2400}
                  height={400}
                />
              </picture>
            </SponsoredLink>
          </SwiperSlide>
        );
      })}
      </Swiper>
      {!!data?.config?.show_dots && realSlidesCount > 1 && (
        <div className="flex justify-center items-center gap-1.5 pb-2 md:pb-0 md:absolute md:bottom-1 md:inset-x-0 md:z-10">
          {Array.from({ length: realSlidesCount }).map((_, index) => (
            <button
              key={index}
              type="button"
              aria-label={t("goToSlide", { number: index + 1 })}
              onClick={() => goTo(index)}
              className={cn(
                "h-1.5 rounded-full transition-all duration-300 cursor-pointer",
                index === activeIndex
                  ? "w-4.5 bg-primary md:w-6 md:bg-main"
                  : "w-1.5 bg-border-color md:w-6",
              )}
            />
          ))}
        </div>
      )}
    </div>
  );
}
