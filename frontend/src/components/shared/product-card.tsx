"use client";
import { IProduct } from "@/types";
import Image from "next/image";
import React, { useRef, useState } from "react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Swiper as SwiperType } from "swiper/types";
import { Button } from "../ui/button";
import {
  ChevronLeft,
  ChevronRight,
  ChevronRightIcon,
  HeartIcon,
  StarIcon,
} from "lucide-react";
import Price from "./Price";
import { Link } from "@/i18n/navigation";
import { Product } from "@/src/features/noon/home/types";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { Spinner } from "../ui/spinner";
import useLocale from "@/src/hooks/use-locale";
import AddToCartButton from "./add-to-cart-button";

type Props = {
  productData: IProduct | Product;
};

const ProductCard = ({ productData }: Props) => {
  const [isWishlisted, setIsWishlisted] = useState<boolean>(
    productData.is_wishlisted,
  );
  const locale = useLocale();
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);
  const {
    addItem: addToWishlist,
    isMutating: isAddingWishlist,
    targetItemMutating: targetAddingWishlist,
    // checkItem,
  } = useWishlistContext();
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
      className="border border-border-color w-37 md:w-40 lg:w-48 xl:w-72 rounded-lg overflow-hidden h-full flex flex-col gap-2 bg-white group"
      onMouseEnter={() => handleAutoplay("start")}
      onMouseLeave={() => handleAutoplay("stop")}
    >
      {/* card top (images slide, topleft badge, wishlist but, cart btn) */}
      <div className="relative h-43 md:h-52 lg:h-60 xl:h-92">
        {/* top left badge */}
        {!!productData?.category_name?.[locale] && (
          <div className="absolute top-0 left-0 rounded-br-lg bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10">
            {productData?.category_name?.[locale]}
          </div>
        )}
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            "absolute top-0 md:top-1 lg:top-2 p-1! right-1 lg:right-2 z-10 rounded-full aspect-square bg-white/60"
          }
          disabled={
            isAddingWishlist && targetAddingWishlist === productData.listing_id
          }
          onClick={(e) => {
            e.preventDefault();
            addToWishlist({
              listingId: productData.listing_id,
              productVariantId: productData.variant_id,
            }).then((d) => setIsWishlisted((p) => p || !!d?.success));
          }}
        >
          {isAddingWishlist &&
          targetAddingWishlist === productData.listing_id ? (
            <Spinner />
          ) : (
            <HeartIcon
              className={`size-4 md:size-6 ${isWishlisted ? "text-red fill-red" : ""} `}
            />
          )}
        </Button>
        {/* cart button */}
        <AddToCartButton listingId={productData.listing_id} />
        {/* navigation buttons */}
        <button
          ref={prevRef}
          className="hidden md:flex absolute top-1/2 inset-s-0 z-10 cursor-pointer opacity-0 group-hover:opacity-35 transition duration-200 bg-black text-white px-0.5 py-2 rounded-e-sm"
        >
          <ChevronLeft size={"28px"} />
        </button>
        <button
          ref={nextRef}
          className="hidden md:flex absolute top-1/2 inset-e-0 z-10 cursor-pointer opacity-0 group-hover:opacity-35 transition duration-200 bg-black text-white px-0.5 py-2 rounded-s-sm"
        >
          <ChevronRight size={"28px"} />
        </button>
        <Swiper
          modules={[Pagination, Autoplay, Navigation]}
          pagination
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
          autoplay={{ delay: 900, disableOnInteraction: true }}
          className="bg-gray-2 h-full"
          onSwiper={(swiper) => {
            swiperRef.current = swiper;
            swiper.autoplay.stop();
          }}
        >
          {productData?.images?.map((image) => (
            <SwiperSlide
              key={image.id}
              className="flex! justify-center! items-center!"
            >
              <Image
                src={image.url}
                alt={image?.alt[locale] as string}
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
            <StarIcon className="size-2 md:size-3 text-green fill-green" />
            <p className="font-semibold text-[8px] md:text-xs ">
              {productData.rating_avg}
            </p>
            <p className="text-gray text-[8px] md:text-xs lg:text-sm">
              ({productData.rating_count})
            </p>
          </div>
          <Price
            currentPrice={productData.price / 100}
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
