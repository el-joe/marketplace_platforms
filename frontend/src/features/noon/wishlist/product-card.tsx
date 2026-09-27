"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { Item, ProductItem } from "@/types/wishlist.type";
import { EllipsisIcon, StarIcon } from "lucide-react";
import Image from "next/image";
import React, { useRef } from "react";
import { Autoplay, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import WishlistItemOptionsMenu from "./item-options-menu";
import AnimatedBadge from "@/src/components/shared/animated-badge";
import { mapPromoBadges } from "@/src/lib/promo-badges";
import CartButton from "../productView/cart-button";
import { ShippingBadgePill } from "@/src/components/shared/shipping-badge-pill";

type Props = {
  item: ProductItem;
};

export default function ProductCard({ item }: Props) {
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
          {!!item.listing.brand && (
            <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
              {item.listing?.brand?.name?.[locale]}
            </div>
          )}
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
            {item?.listing?.product?.images.map((image) => (
              <SwiperSlide key={image.url}>
                <Image
                  src={image.url}
                  alt={
                    locale === "ar"
                      ? item.listing?.product?.name_ar || ""
                      : item.listing.product?.name_en || ""
                  }
                  width={500}
                  height={600}
                  className="max-h-full"
                />
              </SwiperSlide>
            ))}
          </Swiper>
        </div>
        {/* card body (title, rate, price, bottom badge) */}
        <Link href={`/products/${item.listing.listing_id}`} className="flex-1">
          <div className="flex flex-col gap-2 justify-around p-1 lg:p-2.5 h-full">
            {/* title */}
            <h3 className="text-[10px] font-medium md:text-xs lg:text-sm line-clamp-3">
              {locale === "ar"
                ? item.listing?.product?.name_ar
                : item.listing.product?.name_en}
            </h3>
            {/* {!!item.listing.variant_name && (
              <p className="text-[9px] md:text-xs bg-gray-2 border border-border-color py-0.5 px-1 rounded-md w-full line-clamp-1 overflow-hidden">
                {item.listing.variant_name}
              </p>
            )} */}
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
              currency={item?.listing?.currency}
              currentPrice={item?.listing?.price as number}
              size="sm"
            />
            {!!item.listing.promo_badges?.length && (
              <AnimatedBadge
                size="sm"
                badges={mapPromoBadges(item.listing.promo_badges, locale)}
              />
            )}
            {/* bottom badge */}
            {item.listing?.shipping_badge && (
              <ShippingBadgePill
                badge={item?.listing?.shipping_badge}
                locale={locale}
                className="mt-auto"
              />
            )}
          </div>
        </Link>
      </div>
      <div className="flex gap-3">
        <div className="flex-1">
          <CartButton
            listingId={item?.listing?.listing_id as string}
            classes="text-xs md:text-base"
          />
        </div>
        <WishlistItemOptionsMenu
          item={item as Item}
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
