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
} from "lucide-react";
import { getTranslations } from "next-intl/server";
import React from "react";
import { IProductDetails } from "./types";
import getLocale from "@/src/helpers/getLocale";

type Props = {
  product: IProductDetails;
};

export default async function BaseInfo({ product }: Props) {
  const locale = await getLocale();
  const t = await getTranslations("productView");
  return (
    <>
      {/* brand link */}
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
      {/* product name + variant name */}
      <h3 className="text-xl mt-2 mb-2 font-bold">
        {product?.variant?.variant_name || product?.product?.name?.[locale]}
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
        {/* price */}
      </div>
      <div className="flex mt-4 mb-2 gap-2 flex-wrap">
        <Price
          size="xl"
          currentPrice={product.listing.price}
          currency={product.listing.currency}
          // oldPrice={product.oldPrice}
          // discountPercent={product.discount}
        />
        <div className="bg-gray-2 px-2 py-1 flex items-center gap-2 rounded-md">
          <CarIcon className="size-4 text-orange" /> Free Delivery
        </div>
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
      {false && (
        <Link
          href={"#"}
          className="p-2 bg-gray-2 flex rounded-md items-center gap-2 text-sm"
        >
          <div className="flex">
            <span className="w-5 h-5 rounded-full bg-badge-1">
              <StarIcon className="fill-white w-full h-full" />
            </span>
          </div>
          <p className="font-semibold">Best Seller #1</p>
          <p>in</p>
          <p className="text-blue! font-semibold">Lorem, ipsum dolor.</p>
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
