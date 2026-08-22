"use client";
import React from "react";
import { useTranslations } from "next-intl";
import {
  CurrencyCode,
  getCurrencySymbol,
} from "@/src/helpers/get-currency-symbol";

type Props = {
  currentPrice: number | string;
  oldPrice?: number;
  discountPercent?: number;
  size?: "xs" | "sm" | "base" | "lg" | "xl";
  variant?: "cart" | "default";
  className?: string;
  currency?: CurrencyCode;
};

const currentPriceSize = {
  xs: "text-[10px] lg:text-sm",
  sm: "text-base lg:text-lg",
  base: "text-base lg:text-lg",
  lg: "text-lg lg:text-xl",
  xl: "text-xl lg:text-2xl",
};
const oldPriceSize = {
  xs: "text-[8px] lg:text-xs",
  sm: "text-[10px] lg:text-sm",
  base: "text-xs lg:text-base",
  lg: "text-sm lg:text-base",
  xl: "text-base lg:text-lg",
};
const discountPercentSize = {
  xs: "text-[10px] lg:text-sm",
  sm: "text-xs lg:text-base",
  base: "text-sm lg:text-base",
  lg: "text-base lg:text-lg",
  xl: "text-base lg:text-xl",
};

const Price = ({
  currentPrice,
  oldPrice,
  discountPercent,
  size = "base",
  variant = "default",
  className,
  currency = "AED",
}: Props) => {
  const t = useTranslations();
  return (
    <div
      className={`inline-flex items-end font-bold gap-1 ${variant === "cart" ? "flex-col" : ""} ${className}`}
    >
      {/* current price */}
      <div className={`flex items-center ${currentPriceSize[size]}`}>
        {getCurrencySymbol(currency)}
        <p>{currentPrice.toLocaleString()}</p>
      </div>
      {variant === "default" && (
        <>
          {!!oldPrice && (
            <p
              className={`font-semibold line-through text-gray ${oldPriceSize[size]}`}
            >
              {oldPrice.toLocaleString()}
            </p>
          )}
          {discountPercent && (
            <p
              className={`text-green font-semibold ${discountPercentSize[size]}`}
            >
              {discountPercent}% {size === "xl" && t("off")}
            </p>
          )}
        </>
      )}
      {variant === "cart" && (
        <div className="flex flex-row-reverse gap-1">
          {oldPrice && (
            <p
              className={`font-semibold line-through text-gray ${oldPriceSize[size]}`}
            >
              {oldPrice.toLocaleString()}
            </p>
          )}
          {discountPercent && (
            <p
              className={`text-green font-semibold ${discountPercentSize[size]}`}
            >
              {discountPercent}% {size === "xl" && t("off")}
            </p>
          )}
        </div>
      )}
    </div>
  );
};

export default Price;
