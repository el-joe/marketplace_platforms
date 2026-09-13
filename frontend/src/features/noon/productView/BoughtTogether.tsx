"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React, { useEffect, useState } from "react";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "./types";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { Spinner } from "@/src/components/ui/spinner";

export default function BoughtTogether({
  boughtTogetherData,
}: {
  boughtTogetherData: IProductDetails["frequently_bought_together"];
}) {
  const t = useTranslations("productView");
  const locale = useLocale();
  const [selectedItems, setSelectedItems] = useState(
    boughtTogetherData.items.map((item) => item),
  );
  const [isAdded, setIsAdded] = useState(false);
  const { addItemsBulk, isMutating, targetItemMutating } = useCartContext();
  useEffect(() => {}, []);
  return (
    <>
      <h5 className="text-gray font-semibold mb-3">
        {t("frequentlyBoughtTogether")}
      </h5>
      <Swiper
        modules={[Navigation, FreeMode]}
        navigation
        freeMode={true}
        slidesPerView={"auto"}
        spaceBetween={4}
        className="pe-8! border border-border rounded-lg py-4! lg:border-0 lg:py-0!"
      >
        {boughtTogetherData.items.map((product) => {
          const isSelected = !!selectedItems.find(
            (item) => item.listing_id === product.listing_id,
          );
          return (
            <SwiperSlide
              key={product.product_id}
              className="flex! h-auto! w-fit! items-center lg:after:content-['+'] lg:after:px-2 xl:after:text-4xl lg:last:after:opacity-0"
            >
              <div className="lg:p-1 lg:pt-0 lg:bg-gray-2 h-full flex flex-col items-center justify-center gap-1 rounded-md w-36">
                <Checkbox
                  className={
                    "absolute top-3 inset-s-3 size-5 data-checked:bg-blue-2 data-checked:text-white"
                  }
                  checked={isSelected}
                  onClick={(e) => {
                    e.preventDefault();
                    setSelectedItems((p) => {
                      if (isSelected) {
                        return p.filter(
                          (item) => item.listing_id !== product.listing_id,
                        );
                      } else {
                        return [...p, product];
                      }
                    });
                  }}
                />
                {/* <div className="bg-white"> */}
                <Image
                  src={product?.image_url}
                  alt="product image"
                  width={140}
                  height={200}
                  className="mx-auto h-27 object-contain mt-2 w-full bg-white"
                  onClick={(e) => {
                    e.preventDefault();
                    setSelectedItems((p) => {
                      if (isSelected) {
                        return p.filter(
                          (item) => item.listing_id !== product.listing_id,
                        );
                      } else {
                        return [...p, product];
                      }
                    });
                  }}
                />
                {/* </div> */}
                <Link
                  href={`/products/${product.listing_id}`}
                  className="text-center"
                >
                  <Price
                    currentPrice={product.price}
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
          );
        })}
      </Swiper>
      <Button
        variant={"outline"}
        className={
          "w-full mb-4 lg:mb-0 mt-4 text-lg text-blue border-blue py-2"
        }
        disabled={isMutating || selectedItems.length < 2 || isAdded}
        onClick={async () => {
          addItemsBulk(
            selectedItems.map((item) => ({
              vendor_listing_id: item.listing_id,
              quantity: 1,
            })),
          ).then(() => {
            setIsAdded(true);
            setTimeout(() => setIsAdded(false), 3000);
          });
        }}
      >
        {isAdded ? (
          t("addedToTheCart") + "👍"
        ) : (
          <>
            {isMutating && Array.isArray(targetItemMutating) && <Spinner />}
            {t("buy")} {selectedItems.length} {t("togetherFor")}
            <Price
              currentPrice={selectedItems.reduce((p, c) => (p += c.price), 0)}
              currency={boughtTogetherData?.currency}
            />
          </>
        )}
      </Button>
    </>
  );
}
