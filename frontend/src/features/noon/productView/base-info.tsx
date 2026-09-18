"use client";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { RatingStars } from "@/src/components/ui/RatingStars";
import {
  CarIcon,
  // BadgeCheckIcon,
  ChevronLeft,
  ChevronRight,
  CircleStarIcon,
  StarIcon,
  TruckIcon,
} from "lucide-react";
import React from "react";
import { IProductDetails } from "./types";
import { Badge } from "@/src/components/ui/badge";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import Image from "next/image";
import StanderWarrantyDialog from "./dialogs/stander-warranty-dialog";
import AnimatedBadge from "@/src/components/shared/animated-badge";

type Props = {
  product: IProductDetails;
};

const PROMO_BADGE_ICONS: Record<
  string,
  React.ForwardRefExoticComponent<
    Omit<React.ComponentProps<typeof CarIcon>, "ref"> &
      React.RefAttributes<SVGSVGElement>
  >
> = {
  car: CarIcon,
  truck: TruckIcon,
};

export default function BaseInfo({ product }: Props) {
  const locale = useLocale();
  const t = useTranslations("productView");
  const { cart } = useCartContext();
  const isInCart = cart?.cart.items.find(
    (item) => item.listing_id === product.listing.listing_id,
  );
  return (
    <>
      <div className="flex mb-5">
        {product.is_mega_deal && (
          <Badge className="text-sm font-bold bg-[#f5ced7] text-red rounded-sm">
            {t("megaDeal")}
          </Badge>
        )}
        {!!isInCart && (
          <Badge className="text-sm font-bold bg-green text-white ms-auto">
            <Image
              src="/images/shopping-cart-green.svg"
              alt=""
              width={18}
              height={18}
            />{" "}
            {t("inYourCart")}
          </Badge>
        )}
      </div>
      {/* brand link */}
      <div className="flex items-center gap-3 flex-wrap">
        <Link
          href={`/brands/${product.product.brand.slug}`}
          className="flex items-center gap-1 text-blue!"
        >
          {/* <BadgeCheckIcon size={"18px"} /> */}
          <p className="text-lg font-semibold">
            {product.product.brand.name[locale]}
          </p>
          {locale === "ar" ? (
            <ChevronLeft size={"18px"} />
          ) : (
            <ChevronRight size={"18px"} />
          )}
        </Link>

        {product.product.brand.authenticity && (
          <StanderWarrantyDialog
            brandName={product.product.brand.name[locale] as string}
            warrantyYears={
              product.product.brand.authenticity.manufacturer_warranty_months
                ? Math.max(
                    1,
                    Math.round(
                      Number(
                        product.product.brand.authenticity
                          .manufacturer_warranty_months,
                      ) / 12,
                    ),
                  )
                : 1
            }
            countryName={
              product.product.brand.authenticity.covered_country?.[
                locale
              ] as string
            }
            trigger={
              <button className="flex items-center gap-1 text-blue-600 text-sm font-semibold">
                {t("coveredBy", {
                  brand: product.product.brand.name[locale] as string,
                })}
              </button>
            }
          />
        )}
      </div>
      {/* product name + variant name */}
      <h3 className="text-xl mt-2 mb-2 font-bold">
        {product?.variant?.variant_name?.[locale] ||
          product?.product?.name?.[locale]}
      </h3>
      {/* rate */}
      <div className="flex items-center gap-2">
        <p>{product?.product?.rating_avg}</p>
        <RatingStars rating={product?.product?.rating_avg} />
        <Link
          href={"#reviews"}
          className="text-blue! font-semibold border-s ps-2"
        >
          {product?.product?.rating_count} {t("ratings")}
        </Link>
      </div>
      {/* price */}
      <div className="flex mt-8 mb-2 gap-2 flex-wrap">
        <Price
          size="xl"
          currentPrice={product.listing.price}
          currency={product.listing.currency}
          // oldPrice={product.oldPrice}
          // discountPercent={product.discount}
        />
        {!!product.promo_badges?.length && (
          <AnimatedBadge
            badges={product.promo_badges.map((badge) => ({
              label: badge.label[locale],
              icon: PROMO_BADGE_ICONS[badge.icon_key] || CarIcon,
              iconColor: badge.color_hex,
            }))}
            containerClasses="px-2! bg-gray-2! rounded-md!"
          />
        )}
        <Link
          href={`/bestseller/${product.product.category.slug}`}
          className="bg-gray-2 px-3 py-2 mt-2 flex items-center gap-2 rounded-md w-full font-bold"
        >
          <CircleStarIcon className="size-6 fill-purple-500 text-white" />{" "}
          {t("exploreOtherBestsellerIn")}
          <span className="text-blue-2">
            {product.product.category.name[locale]}
          </span>
          {locale === "ar" ? (
            <ChevronLeft className="size-6 ms-auto" />
          ) : (
            <ChevronRight className="size-6 ms-auto" />
          )}
        </Link>
      </div>
      {/* best seller bar */}
      {product.best_seller_badge && (
        <Link
          href={product.best_seller_badge.link_url}
          className="p-2 bg-gray-2 flex rounded-md items-center gap-2 text-sm"
        >
          <div className="flex">
            <span className="w-5 h-5 rounded-full bg-badge-1">
              <StarIcon className="fill-white w-full h-full" />
            </span>
          </div>
          <p className="font-semibold">
            {t("bestSellerRank", { rank: product.best_seller_badge.rank })}
          </p>
          <p>{t("bestSellerIn")}</p>
          <p className="text-blue! font-semibold">
            {locale === "ar"
              ? product.best_seller_badge.category_name_ar
              : product.best_seller_badge.category_name_en}
          </p>
          {locale === "ar" ? (
            <ChevronLeft className="ms-auto" />
          ) : (
            <ChevronRight className="ms-auto" />
          )}
        </Link>
      )}
    </>
  );
}
