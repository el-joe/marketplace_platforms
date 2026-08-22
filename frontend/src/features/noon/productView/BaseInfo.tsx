import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { RatingStars } from "@/src/components/ui/RatingStars";
import {
  // BadgeCheckIcon,
  ChevronLeft,
  ChevronRight,
  StarIcon,
} from "lucide-react";
import { getTranslations } from "next-intl/server";
import React from "react";
import { IProductDetails } from "./types";
import getLocale from "@/src/helpers/getLocale";
import { centsToAmount } from "@/src/lib/utils";

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
      {/* product name */}
      <h3 className="text-xl mt-4 mb-2 font-semibold">
        {product?.product?.name?.[locale]}
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
      <div className="flex mt-4 mb-2">
        <Price
          size="xl"
          currentPrice={centsToAmount(product.listing.price)}
          currency={product.listing.currency}
          // oldPrice={product.oldPrice}
          // discountPercent={product.discount}
        />
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
