"use client";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { getImageURL } from "@/src/helpers/get-image-url";
import type { MarketerBanner } from "../helpers/types";

interface Props {
  banners: MarketerBanner[];
  prevLabel: string;
  nextLabel: string;
}

function trackClick(banner: MarketerBanner) {
  if (!banner.click_url) return;
  fetch(banner.click_url, { method: "POST", keepalive: true }).catch(() => {});
}

export default function MarketerBannersCarousel({ banners, prevLabel, nextLabel }: Props) {
  const isAr = useLocale() === "ar";
  const multiple = banners.length > 1;

  return (
    <div className="marketer-banners group relative overflow-hidden rounded-xl bg-gray-2" dir={isAr ? "rtl" : "ltr"}>
      <Swiper
        key={isAr ? "rtl" : "ltr"}
        dir={isAr ? "rtl" : "ltr"}
        modules={[Autoplay, Navigation, Pagination]}
        loop={multiple}
        autoplay={multiple ? { delay: 5000, disableOnInteraction: false, pauseOnMouseEnter: true } : false}
        pagination={multiple ? { clickable: true } : false}
        navigation={multiple ? { prevEl: ".mb-prev", nextEl: ".mb-next" } : false}
        slidesPerView={1}
      >
        {banners.map((b) => {
          const desktop = getImageURL(b.image_desktop ?? b.image_mobile);
          const mobile = getImageURL(b.image_mobile ?? b.image_desktop);
          const alt = b.title ?? "";
          const picture = (
            <picture>
              <source media="(max-width: 767px)" srcSet={mobile} />
              <img src={desktop} alt={alt} className="block aspect-[2/1] w-full object-cover md:aspect-[1000/280]" />
            </picture>
          );
          const internal = !!b.link && b.link.startsWith("/");
          return (
            <SwiperSlide key={b.id}>
              {!b.link ? (
                picture
              ) : internal ? (
                <Link href={b.link} onClick={() => trackClick(b)} aria-label={alt}>
                  {picture}
                </Link>
              ) : (
                <a href={b.link} target="_blank" rel="noopener noreferrer" onClick={() => trackClick(b)} aria-label={alt}>
                  {picture}
                </a>
              )}
            </SwiperSlide>
          );
        })}
      </Swiper>

      {multiple && (
        <>
          <button
            type="button"
            aria-label={prevLabel}
            className="mb-prev absolute start-2 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow transition hover:bg-white md:flex"
          >
            {isAr ? <ChevronRight className="size-5" /> : <ChevronLeft className="size-5" />}
          </button>
          <button
            type="button"
            aria-label={nextLabel}
            className="mb-next absolute end-2 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow transition hover:bg-white md:flex"
          >
            {isAr ? <ChevronLeft className="size-5" /> : <ChevronRight className="size-5" />}
          </button>
        </>
      )}
    </div>
  );
}
