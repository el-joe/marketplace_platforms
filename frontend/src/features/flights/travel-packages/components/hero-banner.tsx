"use client";

import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay } from "swiper/modules";
import type { TravelPackageSummary } from "../../helpers/types";

type Props = {
  packages: TravelPackageSummary[];
};

export default function HeroBanner({ packages }: Props) {
  if (packages.length === 0) return null;

  return (
    <div className="relative h-[250px] sm:h-72 lg:h-96 overflow-hidden">
      <Swiper
        modules={[Autoplay]}
        autoplay={{ delay: 4000, disableOnInteraction: false }}
        loop
        className="h-full"
      >
        {packages.map((pkg) => (
          <SwiperSlide key={pkg.package_id}>
            <Image
              src={pkg.thumbnail}
              alt={pkg.destination_city}
              fill
              priority
              sizes="100vw"
              className="object-cover"
            />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
}
