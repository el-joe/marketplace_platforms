"use client";
import { Button } from "@/src/components/ui/button";
import { ChevronLeft, ChevronRight, HeartIcon } from "lucide-react";
import Image from "next/image";
import React, { useEffect, useRef, useState } from "react";
import { FreeMode, Navigation, Pagination, Thumbs } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper/types";
import { IProductDetails } from "./types";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { Spinner } from "@/src/components/ui/spinner";

type Props = {
  product: IProductDetails;
};

export default function ProductImagesPreview({ product }: Props) {
  const [isWishlisted, setIsWishlisted] = useState<boolean>(
    product.listing.is_wishlisted,
  );
  const [thumbsSwiper, setThumbsSwiper] = useState<SwiperType>();
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);
  const { addItem, isMutating, checkItem } = useWishlistContext();
  useEffect(() => {
    (async () => {
      const { data } = await checkItem(product.listing.listing_id);
      const { in_wishlist } = data;
      setIsWishlisted(in_wishlist);
    })();
  }, [checkItem, product.listing.listing_id]);
  return (
    <div>
      <div className="md:px-8 xl:px-20 relative">
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            " absolute top-0 md:top-1 lg:top-2 p-0! right-1 lg:right-2 z-10 rounded-full aspect-square"
          }
          disabled={isMutating}
          onClick={() => {
            addItem({
              listingId: product.listing.listing_id,
              productVariantId: product.variant.id,
            });
          }}
        >
          {isMutating ? (
            <Spinner />
          ) : (
            <HeartIcon
              className={`size-4 md:size-6 ${isWishlisted ? "text-red fill-red" : ""} `}
            />
          )}
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
          modules={[Navigation, Thumbs, Pagination]}
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
          slidesPerView={1}
          thumbs={{
            swiper: thumbsSwiper,
          }}
        >
          {product.product.images.map((image, i) => (
            <SwiperSlide
              key={i}
              className="select-none md:h-[calc(100vh-260px)]! max-h-200"
            >
              <Image
                src={image.url || "/images/no-image-available-icon.jpg"}
                alt=""
                width={400}
                height={450}
                className="mx-auto h-full object-contain "
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
          {product.product.images.map((image, i) => (
            <SwiperSlide
              key={i}
              className="w-fit! h-24! cursor-pointer opacity-45 border-2 rounded-lg overflow-hidden"
            >
              <Image
                src={image.url || "/images/no-image-available-icon.jpg"}
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
