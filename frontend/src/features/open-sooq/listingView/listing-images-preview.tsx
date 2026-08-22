"use client";
import { Button } from "@/src/components/ui/button";
import { IProduct } from "@/types";
import { ChevronLeft, ChevronRight, HeartIcon } from "lucide-react";
import Image from "next/image";
import React, { useRef, useState } from "react";
import { FreeMode, Navigation, Pagination, Thumbs } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper/types";

type Props = {
  images: string[];
};

export default function ProductImagesPreview({ images }: Props) {
  const [thumbsSwiper, setThumbsSwiper] = useState<SwiperType>();
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);

  return (
    <div>
      <div className="md:px-8 xl:px-20 relative">
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            " absolute top-0 md:top-1 lg:top-2 p-0! right-1 lg:right-2 z-10 rounded-full aspect-square"
          }
        >
          <HeartIcon
            className={`size-4 md:size-6 ${true ? "text-red fill-red" : ""} `}
          />
        </Button>
        {/* navigation buttons */}
        <button
          ref={prevRef}
          className="hidden md:flex absolute top-1/2 inset-s-0 z-10 cursor-pointer opacity-35 bg-black text-white px-2 py-6 rounded-e-sm"
        >
          <ChevronLeft size={"28px"} />
        </button>
        <button
          ref={nextRef}
          className="hidden md:flex absolute top-1/2 inset-e-0 z-10 cursor-pointer opacity-35 bg-black text-white px-2 py-6 rounded-s-sm"
        >
          <ChevronRight size={"28px"} />
        </button>
        {/* preview image */}
        <Swiper
          modules={[Navigation, Thumbs]}
          loop
          onBeforeInit={(swiper) => {
            if (
              swiper.params.navigation &&
              typeof swiper.params.navigation !== "boolean"
            ) {
              swiper.params.navigation.prevEl = prevRef.current;
              swiper.params.navigation.nextEl = nextRef.current;
            }
          }}
          slidesPerView={1}
          thumbs={{
            swiper: thumbsSwiper,
          }}
        >
          {images.map((image) => (
            <SwiperSlide key={image} className="select-none">
              <Image
                src={image}
                alt=""
                width={400}
                height={450}
                className="mx-auto max-h-[calc(100vh-240px)] object-contain"
              />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
      {/* pagination thumbs */}
      <div className="hidden md:block">
        <Swiper
          className="p-2! productSwiperThumbs h-30"
          onSwiper={setThumbsSwiper}
          spaceBetween={"9px"}
          slidesPerView={"auto"}
          freeMode={true}
          watchSlidesProgress={true}
          loop
          modules={[FreeMode, Thumbs]}
        >
          {images.map((image) => (
            <SwiperSlide
              key={image}
              className="w-fit! h-24! cursor-pointer opacity-45 border-2 rounded-lg overflow-hidden"
            >
              <Image
                src={image}
                alt=""
                width={92}
                height={120}
                className="max-h-full w-18.75"
              />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
    </div>
  );
}
