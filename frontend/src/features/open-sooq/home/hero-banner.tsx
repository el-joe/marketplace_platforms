"use client";
import Image from "next/image";
import React from "react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import { useTranslations } from "next-intl";

const leftBanner = [
  "https://eg.opensooq.com/_next/image?url=https%3A%2F%2Fopensooq-images.os-cdn.com%2Foriginals%2Fmedia%2F40%2F35%2F40352566628d710e079bc8de7d4c29b55a395311b51b2921050a16f408b95022.png&w=1080&q=65",
];

const HeroBanner = () => {
  const t = useTranslations("openSooq.home");
  return (
    <div className="bg-section-bg md:bg-background container px-0 md:px-4 py-3 md:py-0">
      <div className="flex">
        {/* bottom left banner */}
        <Swiper
          modules={[Navigation, Pagination, Autoplay]}
          // navigation
          pagination={{
            bulletClass:
              "w-[5px] h-[5px] md:w-6! md:h-1.5! rounded-full inline-block bg-border-color me-1 cursor-pointer",
            bulletActiveClass: "bg-gray! md:bg-main! w-2! h-2! md:w-6 md:h-1.5",
            clickableClass: "w-fit! left-1/2! -translate-x-1/2",
            clickable: true,
          }}
          loop
          autoplay={{ delay: 3000, pauseOnMouseEnter: true }}
          speed={1500}
          className="flex-1 pb-6! md:pb-0!"
          spaceBetween={20}
          slidesPerView={1}
          breakpoints={{
            768: {
              spaceBetween: 0,
            },
          }}
        >
          {leftBanner.map((banner) => (
            <SwiperSlide key={banner}>
              <Link
                href={"/"}
                className="h-44 xl:h-60 2xl:h-full block relative"
              >
                <Image
                  src={banner}
                  alt="banner"
                  fill
                  sizes="100%"
                  className="rounded-4xl md:rounded-none"
                />
              </Link>
            </SwiperSlide>
          ))}
        </Swiper>
        {/* bottom right banner */}
        <div className="h-44 xl:h-60 2xl:h-66  hidden md:flex items-center">
          <Link
            href={"/"}
            className="w-44 xl:w-60 2xl:w-64 py-2 h-full block relative text-center"
          >
            <p className="text-xl font-semibold text-light">
              {t("sellAnythingDescription")}
            </p>
            <Image
              src="https://opensooqui2.os-cdn.com/prod/public/images/homePage/homeBanner-1.webp"
              alt=""
              width={416}
              height={220}
              className="object-contain"
            />
            <Button className={"text-xl bg-green px-6 py-2 text-white"}>
              {t("sellAnythingNow")}
            </Button>
          </Link>
          <Link
            href={"/"}
            className="w-44 xl:w-60 2xl:w-64 py-2 h-full block relative text-center"
          >
            <p className="text-xl font-semibold text-light">
              {t("searchNowDescription")}
            </p>
            <Image
              src={
                "https://opensooqui2.os-cdn.com/prod/public/images/homePage/homeBanner-2.webp"
              }
              alt=""
              width={416}
              height={220}
              className="object-contain"
            />
            <Button className={"text-xl bg-main px-6 py-2"}>
              {t("searchNow")}
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
};

export default HeroBanner;
