"use client";
import Image from "next/image";
import React, { useRef } from "react";
import { Autoplay, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import { Button } from "../ui/button";
import { HeartIcon } from "lucide-react";
import Price from "./Price";
import { Link } from "@/i18n/navigation";

export interface IDemoListing {
  id: number;
  images: string[];
  title: string;
  price: number;
  shortDescription: string;
  status: "new" | "used";
  phone: string;
  location: string;
}

type Props = {
  listingData: IDemoListing;
};

const ListingCard = ({ listingData }: Props) => {
  const swiperRef = useRef<null | SwiperType>(null);
  const handleAutoplay = (state: "start" | "stop") => {
    const swiper = swiperRef.current;
    if (!swiper) return;
    if (state === "start") {
      swiper.autoplay.start();
    } else {
      swiper.autoplay.stop();
      swiper.slideTo(0);
    }
  };
  return (
    <div
      className="border border-border-color w-37 md:w-40 lg:w-48 xl:w-72 rounded-lg overflow-hidden h-full flex flex-col gap-2"
      onMouseEnter={() => handleAutoplay("start")}
      onMouseLeave={() => handleAutoplay("stop")}
    >
      {/* card top (images slide, topleft badge, wishlist but, cart btn) */}
      <div className="relative h-43 md:h-52 lg:h-60 xl:h-92">
        {/* top left badge */}

        <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
          {listingData.status}
        </div>

        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            "absolute top-0 md:top-1 lg:top-2 p-0! right-1 lg:right-2 z-10 rounded-full aspect-square"
          }
        >
          <HeartIcon
            className={`size-4 md:size-6 ${true ? "text-red fill-red" : ""} `}
          />
        </Button>
        <Swiper
          modules={[Pagination, Autoplay]}
          pagination
          autoplay={{ delay: 900, disableOnInteraction: true }}
          className="bg-gray-2 h-full"
          onSwiper={(swiper) => {
            swiperRef.current = swiper;
            swiper.autoplay.stop();
          }}
        >
          {listingData.images.map((image, i) => (
            <SwiperSlide key={i} className="flex! place-items-center">
              <Image
                src={image}
                alt={listingData.title}
                width={500}
                height={600}
                className="max-h-full object-cover"
              />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
      {/* card body (title, rate, price, bottom badge) */}
      <Link href={`/find/${listingData.id}`}>
        <div className="flex flex-col gap-2 justify-around p-1 lg:p-2.5 flex-1">
          {/* title */}
          <h3 className="text-[10px] font-medium md:text-xs lg:text-sm line-clamp-3">
            {listingData.title}
          </h3>
          <p className="text-sm text-light">{listingData.shortDescription}</p>
          <Price currentPrice={listingData.price} size="sm" />
        </div>
      </Link>
    </div>
  );
};

export default ListingCard;
