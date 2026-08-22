"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "./types";
import { centsToAmount } from "@/src/lib/utils";
import useLocale from "@/src/hooks/use-locale";

const products = [
  {
    id: 1,
    image:
      "https://f.nooncdn.com/p/pzsku/Z6DDF492F196DEEEE71EBZ/45/_/1772091246/7e017007-5102-4ba4-8dc2-a36f28a8e364.jpg?width=480",
    price: 1230,
    title: "Samsung Galaxy S25 Ultra AI",
    shippingIcon: "/images/supermall-delivery.svg",
  },
  {
    id: 2,
    image:
      "https://f.nooncdn.com/p/pzsku/Z6DDF492F196DEEEE71EBZ/45/_/1772091246/7e017007-5102-4ba4-8dc2-a36f28a8e364.jpg?width=480",
    price: 1230,
    title: "Samsung Galaxy S25 Ultra AI",
    shippingIcon: "/images/supermall-delivery.svg",
  },
  {
    id: 3,
    image:
      "https://f.nooncdn.com/p/pzsku/Z6DDF492F196DEEEE71EBZ/45/_/1772091246/7e017007-5102-4ba4-8dc2-a36f28a8e364.jpg?width=480",
    price: 1230,
    title: "Samsung Galaxy S25 Ultra AI",
    shippingIcon: "/images/express-delivery.svg",
  },
  {
    id: 4,
    image:
      "https://f.nooncdn.com/p/pzsku/Z6DDF492F196DEEEE71EBZ/45/_/1772091246/7e017007-5102-4ba4-8dc2-a36f28a8e364.jpg?width=480",
    price: 1230,
    title: "Samsung Galaxy S25 Ultra AI",
    shippingIcon: "/images/supermall-delivery.svg",
  },
];

export default function BoughtTogether({
  boughtTogetherData,
}: {
  boughtTogetherData: IProductDetails["frequently_bought_together"];
}) {
  const t = useTranslations("productView");
  const locale = useLocale();
  return (
    <>
      <h5 className="text-gray font-semibold mb-3">
        {t("frequentlyBoughtTogether")}
      </h5>
      <Swiper
        modules={[Navigation, FreeMode]}
        navigation
        freeMode={true}
        slidesPerView={2.3}
        spaceBetween={14}
        breakpoints={{
          1024: {
            slidesPerView: 3.5,
            spaceBetween: 0,
          },
        }}
        className="pe-8! border border-border rounded-lg py-4! lg:border-0 lg:py-0!"
      >
        {boughtTogetherData.items.map((product) => (
          <SwiperSlide
            key={product.product_id}
            className="flex! h-auto! items-center lg:after:content-['+'] lg:after:px-2 xl:after:text-4xl lg:last:after:opacity-0"
          >
            <div className="lg:p-1 lg:bg-gray-2 h-full flex flex-col items-center justify-center gap-1 rounded-md">
              <Checkbox
                className={"absolute top-3 inset-s-3"}
                checked
                onClick={(e) => e.preventDefault()}
              />
              {/* <div className="bg-white"> */}
              <Image
                src={product?.image_url}
                alt="product image"
                width={140}
                height={200}
                className="mx-auto h-27 object-contain mt-2"
              />
              {/* </div> */}
              <Link
                href={`/products/${product.listing_id}`}
                className="text-center"
              >
                <Price
                  currentPrice={product.price_formatted}
                  // currentPrice={centsToAmount(product?.price)}
                  currency={product.currency}
                />
                <p className="text-sm text-gray text-center line-clamp-2">
                  {product.name[locale]}
                </p>
              </Link>
              <Image
                src={"/images/express-delivery.svg"}
                className="mt-auto"
                alt="shipping icon"
                width={58}
                height={42}
              />
            </div>
          </SwiperSlide>
        ))}
      </Swiper>
      <Button
        variant={"outline"}
        className={
          "w-full mb-4 lg:mb-0 mt-4 text-lg text-blue border-blue py-2"
        }
      >
        {t("buy")} {boughtTogetherData?.items.length} {t("togetherFor")}{" "}
        <Price
          currentPrice={centsToAmount(boughtTogetherData?.total_price)}
          currency={boughtTogetherData?.currency}
        />
      </Button>
    </>
  );
}
