"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { Item } from "@/types/wishlist.type";
import { EllipsisIcon, StarIcon } from "lucide-react";
import Image from "next/image";
import React, { useRef } from "react";
import { Autoplay, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import WishlistItemOptionsMenu from "./item-options-menu";
import CartButton from "../productView/cart-button";

type Props = {
  item: Item;
};

export default function ItemCard({ item }: Props) {
  const locale = useLocale();
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
    <div className="flex flex-col h-auto gap-1">
      <div
        className="border border-border-color w-37 md:w-40 lg:w-48 xl:w-72 rounded-lg overflow-hidden flex-1 flex flex-col gap-2"
        onMouseEnter={() => handleAutoplay("start")}
        onMouseLeave={() => handleAutoplay("stop")}
      >
        {/* card top (images slide, topleft badge, wishlist but, cart btn) */}
        <div className="relative h-43 md:h-52 lg:h-60 xl:h-92">
          {/* top left badge */}
          {/* {item.label && (
            <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
              {item.label}
            </div>
          )} */}
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
            {item.listing.product?.images.map((image) => (
              <SwiperSlide key={image.url}>
                <Image
                  src={image.url}
                  alt={item.listing.product.name_en}
                  width={500}
                  height={600}
                  className="max-h-full"
                />
              </SwiperSlide>
            ))}
          </Swiper>
        </div>
        {/* card body (title, rate, price, bottom badge) */}
        <Link href={`/products/${item.listing.listing_id}`}>
          <div className="flex flex-col gap-2 justify-around p-1 lg:p-2.5 flex-1">
            {/* title */}
            <h3 className="text-[10px] font-medium md:text-xs lg:text-sm line-clamp-3">
              {locale === "ar"
                ? item.listing?.product.name_ar
                : item.listing.product.name_en}
            </h3>
            {/* rating */}
            <div className="bg-gray-2 rounded-md flex items-center gap-1 w-fit px-2 py-px">
              <StarIcon size={"13px"} className="text-green fill-green" />
              <p className="font-semibold text-[8px] md:text-xs">
                {item.listing.rating_avg}
              </p>
              <p className="text-gray text-[8px] md:text-xs">
                ({item.listing.rating_count})
              </p>
            </div>
            <Price
              currency={item.listing.currency}
              currentPrice={item.listing.price}
              size="sm"
            />
            {/* bottom badge */}
            {/* <div className="flex w-fit bg-blue font-semibold text-white rounded-md items-center text-[9px] lg:text-xs gap-1">
              <span>⚡GET IN </span>
              <span className="text-yellow-400"> 33 MINS</span>
              <ChevronRightIcon className="size-3 lg:size-5" />
            </div> */}
          </div>
        </Link>
      </div>
      <div className="flex gap-3">
        <div className="flex-1">
          <CartButton listingId={item.listing.listing_id} />
        </div>
        <WishlistItemOptionsMenu
          item={item}
          trigger={
            <Button variant={"outline"} className={"border-blue "}>
              <EllipsisIcon />
            </Button>
          }
        />
      </div>
    </div>
  );
}
