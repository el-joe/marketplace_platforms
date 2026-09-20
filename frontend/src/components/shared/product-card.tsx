"use client";
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
} from "lucide-react";
import Price from "./Price";
import { Link } from "@/i18n/navigation";
import { IProduct } from "@/types";
import { Product } from "@/types/globals";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { Spinner } from "../ui/spinner";
import useLocale from "@/src/hooks/use-locale";
import AddToCartButton from "./add-to-cart-button";
import { useTranslations } from "next-intl";
import { getImageURL } from "@/src/helpers/get-image-url";
import { AdBadge } from "./ad-badge";
import AnimatedBadge from "./animated-badge";
import { mapPromoBadges } from "@/src/lib/promo-badges";
import useCountDown from "@/src/hooks/useCountDown";
import InternationalShippingIndicator from "./international-shipping-indicator";
import { ProductCardRate } from "../ui/rating/product-card-rate";
import { cn } from "@/src/lib/utils";

type Props = {
  productData: Product | IProduct;
};

const ProductCard = ({ productData }: Props) => {
  const [isWishlisted, setIsWishlisted] = useState<boolean>(
    productData.is_wishlisted,
  );
  const locale = useLocale();
  const t = useTranslations("productView");
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);
  const {
    addItem: addToWishlist,
    isMutating: isAddingWishlist,
    targetItemMutating: targetAddingWishlist,
    // checkItem,
  } = useWishlistContext();
  const swiperRef = useRef<null | SwiperType>(null);
  const { H, M } = useCountDown(
    new Date(productData.flash_sale_ends_at || new Date().getDate()),
  );
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
      <div className="relative h-[300px]">
        {/* sponsored/ad badge */}
        {!!productData.is_sponsored && <AdBadge />}
        {/* top left badge */}
        {!!productData?.category_name?.[locale] && (
          <div
            className={cn(
              "absolute top-0 inset-s-0-0 bg-green-2 text-white px-3.5 py-0.5 text-[8px] md:text-xs lg:text-sm line-clamp-1 max-w-full z-10",
              locale === "ar" ? "rounded-bl-lg" : "rounded-br-lg",
            )}
          >
            {productData?.category_name?.[locale]}
          </div>
        )}
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={
            "absolute top-2 p-1! inset-e-1 lg:inset-e-2 z-10 rounded-full aspect-square bg-white/60"
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
        <AddToCartButton
          listingId={productData.listing_id}
          listingType={productData.listing_type}
          hasCustomAttributes={!!productData.has_custom_attributes}
          customAttributes={productData.custom_attributes ?? []}
        />
        {/* navigation buttons */}
        <button
          ref={prevRef}
          className="hidden md:flex absolute top-1/2 inset-s-0 z-10 cursor-pointer opacity-0 group-hover:opacity-35 transition duration-200 bg-black text-white px-0.5 py-2 rounded-e-sm"
        >
          {locale === "ar" ? (
            <ChevronRight size={"28px"} />
          ) : (
            <ChevronLeft size={"28px"} />
          )}
        </button>
        <button
          ref={nextRef}
          className="hidden md:flex absolute top-1/2 inset-e-0 z-10 cursor-pointer opacity-0 group-hover:opacity-35 transition duration-200 bg-black text-white px-0.5 py-2 rounded-s-sm"
        >
          {locale === "ar" ? (
            <ChevronLeft size={"28px"} />
          ) : (
            <ChevronRight size={"28px"} />
          )}
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
              <ProductImage image={image} locale={locale} />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
      {/* admin listing badge */}
      {/* {productData.listing_type === "admin" && (
        <span className="px-1 lg:px-2.5 text-[9px] md:text-xs text-yellow-600 font-bold uppercase tracking-wide">
          {t("noonExpress")}
        </span>
      )} */}
      {/* marketer attribution — outside the card Link to avoid nested anchors */}
      {/* {"marketer" in productData && productData.marketer?.profile_url && (
        <a
          href={productData.marketer.profile_url}
          className="px-1 lg:px-2.5 text-[9px] md:text-xs text-yellow-600 hover:underline font-medium"
        >
          {productData.marketer.name}
        </a>
      )} */}
      {/* {"campaign_context" in productData &&
        productData.campaign_context?.vendor_name && (
          <span className="px-1 lg:px-2.5 text-[8px] md:text-[10px] text-blue-500 font-medium">
            🛍 {productData.campaign_context.vendor_name}
          </span>
        )} */}
      {/* card body (title, rate, price, bottom badge) */}
      {/* <Link href={`/products/${productData.id}`}> */}
      <Link href={`/products/${productData.url_param}`} className="flex-1">
        <div className="flex flex-col justify-start p-1 lg:p-2.5 h-full gap-2">
          {/* title */}
          <h3 className="text-[10px] font-medium md:text-xs lg:text-base line-clamp-3">
            {locale === "ar" ? productData.name_ar : productData.name_en}
          </h3>

          {/* rating */}
          <ProductCardRate
            rating={productData.rating_avg}
            reviewCount={productData.rating_count}
            className="w-fit mb-1"
          />
          <Price
            currentPrice={productData.price}
            currency={productData.currency}
            oldPrice={Number(productData.compare_at_price)}
            size="sm"
          />
          <InternationalShippingIndicator
            data={productData.international_shipping}
            size="sm"
          />
          {!!productData.promo_badges?.length && (
            <AnimatedBadge
              size="sm"
              badges={mapPromoBadges(productData.promo_badges, locale)}
              containerClasses="mb-1"
            />
          )}
          {/* mega deal / flash sale badge */}
          {productData.is_flash_sale ? (
            <div className="flex w-fit font-semibold text-red bg-[#f5ced7] rounded-md items-center text-[9px] lg:text-xs gap-1 px-1.5 py-0.5 mb-1">
              <span>{t("flashSale")}</span>
              {!!productData.flash_sale_ends_at && (
                <span>
                  {H}h {M}m
                </span>
              )}
            </div>
          ) : (
            !!productData.is_mega_deal && (
              <div className="flex w-fit font-semibold text-red bg-[#f5ced7] rounded-md items-center text-[9px] lg:text-xs gap-1 px-1.5 py-0.5 mb-1">
                <span>{t("megaDeal")}</span>
              </div>
            )
          )}
          {/* bottom badge */}
          {!!productData.shipping_badge && (
            <div
              className="flex w-fit font-semibold text-white rounded-md items-center text-[9px] lg:text-xs gap-1 mt-auto"
              style={{
                background: productData?.shipping_badge?.color_hex,
                color: productData?.shipping_badge?.text_color_hex,
              }}
            >
              <span>⚡{t("getIn")} </span>
              {(() => {
                const days =
                  productData?.shipping_badge?.delivery_days_min ??
                  productData?.shipping_badge?.delivery_days_max;
                return days != null ? (
                  <span>{t("$day", { value: days })}</span>
                ) : (
                  <span>
                    {productData?.shipping_badge?.label?.[locale] ??
                      productData?.shipping_badge?.label?.en}
                  </span>
                );
              })()}
              <ChevronRightIcon className="size-3 lg:size-5 rtl:rotate-180" />
            </div>
          )}
        </div>
      </Link>
    </div>
  );
};

export default ProductCard;

const ProductImage = ({
  image,
  locale,
}: {
  image: Product["images"][number];
  locale: "ar" | "en";
}) => {
  return (
    <Image
      src={getImageURL(image.url)}
      alt={image?.alt?.[locale] || ("" as string)}
      width={500}
      height={300}
      className="h-full"
    />
  );
};
