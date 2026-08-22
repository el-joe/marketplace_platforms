"use client";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";
import { spotlightDealsData } from "@/public/dummyData";
import SpotlightCard from "./SpotlightCard";
import { Autoplay, Navigation } from "swiper/modules";

const SpotlightDeals = () => {
  const t = useTranslations("home");
  return (
    <div className="lg:container">
      <div className="h-8 md:h-12 lg:h-19 relative">
        <Image
          src={
            "https://a.nooncdn.com/mpcms/EN0001/assets/73f4ada1-66b0-4260-9266-6de1c76a5954.png?width=2400"
          }
          fill
          sizes="100%"
          alt="header"
        />
      </div>
      <Swiper
        modules={[Navigation, Autoplay]}
        autoplay={{ delay: 0 }}
        speed={6000}
        breakpoints={{
          768: {
            autoplay: false,
          },
        }}
        wrapperClass="swiper-auto-play-linear"
        className="bg-[#fffcdf] p-4!"
        slidesPerView={"auto"}
        spaceBetween={28}
        navigation
        loop
      >
        {spotlightDealsData.map((deal, i) => (
          <SwiperSlide key={i} className="w-fit!">
            <SpotlightCard data={deal} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default SpotlightDeals;
