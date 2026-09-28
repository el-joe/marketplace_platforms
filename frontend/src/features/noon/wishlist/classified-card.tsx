"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { ClassifiedItem, Item } from "@/types/wishlist.type";
import { EllipsisIcon, EyeIcon, StarIcon } from "lucide-react";
import Image from "next/image";
import React, { useRef } from "react";
import { Autoplay, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import WishlistItemOptionsMenu from "./item-options-menu";
import AnimatedBadge from "@/src/components/shared/animated-badge";
import { mapPromoBadges } from "@/src/lib/promo-badges";
import { ShippingBadgePill } from "@/src/components/shared/shipping-badge-pill";

type Props = {
  item: ClassifiedItem;
};

export default function ClassifiedCard({ item }: Props) {
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
    <div className="flex flex-col h-auto gap-1 w-[calc((100%-12px)/2)] md:w-40 lg:w-48 xl:w-72">
      <div
        className="border border-border-color w-full rounded-lg overflow-hidden flex-1 flex flex-col gap-2"
        onMouseEnter={() => handleAutoplay("start")}
        onMouseLeave={() => handleAutoplay("stop")}
      >
        {/* card top (images slide, topleft badge, wishlist but, cart btn) */}
        <div className="relative h-43 md:h-52 lg:h-60 xl:h-92">
          {/* top left badge */}
          {/* {!!item.listing.city && (
            <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
              {item.listing?.city?.[locale]}
            </div>
          )} */}

          <Image
            src={item.listing.primary_image}
            alt={item.listing?.title?.[locale] as string}
            width={500}
            height={600}
            className="max-h-full"
          />
        </div>
        {/* card body (title, rate, price, bottom badge) */}
        <Link
          href={`/classified/find/${item.listing.listing_number}`}
          className="flex-1"
        >
          <div className="flex flex-col gap-2 justify-around p-1 lg:p-2.5 h-full">
            {/* title */}
            <h3 className="text-[10px] font-medium md:text-xs lg:text-sm line-clamp-3">
              {item.listing?.title?.[locale]}
            </h3>
            {!!item.listing.city && (
              <p className="text-[9px] md:text-xs bg-gray-2 border border-border-color py-0.5 px-1 rounded-md w-full line-clamp-1 overflow-hidden">
                {item.listing?.city?.[locale]}
              </p>
            )}
            {/* view count */}
            <div className="bg-gray-2 rounded-md flex items-center gap-1 w-fit px-2 py-px">
              <EyeIcon size={"13px"} className="text-green" />
              <p className="font-semibold text-[8px] md:text-xs">
                {item.listing.views_count}
              </p>
            </div>
            <Price currentPrice={item?.listing?.price as number} size="sm" />
          </div>
        </Link>
      </div>
      <div className="min-h-11!">
        <WishlistItemOptionsMenu
          item={item as Item}
          trigger={
            <Button variant={"outline"} className={"border-blue h-full w-full"}>
              <EllipsisIcon />
            </Button>
          }
        />
      </div>
    </div>
  );
}
