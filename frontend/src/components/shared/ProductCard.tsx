"use client";
import { IProduct } from "@/types";
import Image from "next/image";
import React, { useRef } from "react";
import { Autoplay, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import { Button } from "../ui/button";
import { ChevronRightIcon, HeartIcon, PlusIcon, StarIcon } from "lucide-react";
import Price from "./Price";
import { Link } from "@/i18n/navigation";
import { Product } from "@/src/features/noon/home/types";

type Props = {
  productData: IProduct | Product;
};

const ProductCard = ({ productData }: Props) => {
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
        {!!productData.vendor?.store_name && (
          <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
            {productData.vendor?.store_name}
          </div>
        )}
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            "absolute top-0 md:top-1 lg:top-2 p-0! right-1 lg:right-2 z-10 rounded-full aspect-square"
          }
        >
          <HeartIcon
            className={`size-4 md:size-6 ${productData.is_wishlisted ? "text-red fill-red" : ""} `}
          />
        </Button>
        {/* cart button */}
        <Button
          variant={"outline"}
          className={
            "absolute bottom-1 lg:bottom-2 right-2 z-10 p-1! xl:p-3! min-w-0! min-h-0! aspect-square"
          }
        >
          <PlusIcon className={`size-4 lg:size-6 `} />
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
          {[productData.thumbnail].map((image) => (
            <SwiperSlide key={image}>
              <Image
                src={image}
                alt={productData.name_en}
                width={500}
                height={600}
                className="max-h-full"
              />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
      {/* card body (title, rate, price, bottom badge) */}
      {/* <Link href={`/products/${productData.id}`}> */}
      <Link href={`/products/${productData.url_param}`}>
        <div className="flex flex-col gap-2 justify-around p-1 lg:p-2.5 flex-1">
          {/* title */}
          <h3 className="text-[10px] font-medium md:text-xs lg:text-sm line-clamp-3">
            {productData.name_en}
          </h3>
          {/* rating */}
          <div className="bg-gray-2 rounded-md flex items-center gap-1 w-fit px-2 py-px md:py-0.5">
            <StarIcon className="size-2 md:size-3 lg:size-4 text-green fill-green" />
            <p className="font-semibold text-[8px] md:text-xs lg:text-base ">
              {productData.rating_avg}
            </p>
            <p className="text-gray text-[8px] md:text-xs lg:text-base">
              ({productData.rating_count})
            </p>
          </div>
          <Price
            currentPrice={productData.price}
            // discountPercent={productData.discount}
            // oldPrice={productData.oldPrice}
            currency={productData.currency}
            size="sm"
          />
          {/* bottom badge */}
          <div className="flex w-fit bg-blue font-semibold text-white rounded-md items-center text-[9px] lg:text-xs gap-1">
            <span>⚡GET IN </span>
            <span className="text-yellow-400"> 33 MINS</span>
            <ChevronRightIcon className="size-3 lg:size-5" />
          </div>
        </div>
      </Link>
    </div>
  );
};

export default ProductCard;
