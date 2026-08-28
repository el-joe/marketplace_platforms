"use client";
import { Input } from "@/src/components/ui/base-inputs/input";
import {
  BadgePercentIcon,
  ChevronLeft,
  ChevronRight,
  Tag,
  X,
} from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import React, { useState } from "react";
import CouponsListModal from "./coupons-list-modal";
import { useCartContext } from "@/src/providers/cart-provider";
import { Button } from "@/src/components/ui/button";
import { FieldError } from "@/src/components/ui/field";
import { Spinner } from "@/src/components/ui/spinner";

export default function CouponInputCard() {
  const t = useTranslations("cart");
  const locale = useLocale();
  const { applyCoupon, applyCouponErr, removeCoupon, cart, isMutating } =
    useCartContext();
  const [couponInputValue, setCouponInputValue] = useState<string>("");
  return (
    <div className="p-4 rounded-[16px] bg-white">
      <h3 className="font-bold mb-3">{t("gotACoupon")}</h3>
      {!!cart?.cart.coupon ? (
        <div>
          <div className="flex gap-2 items-center bg-light-green text-green border border-green rounded-lg px-3 py-2">
            <Tag className="size-4" />
            <p className="font-bold tracking-wider text-lg">
              {cart?.cart.coupon?.code ?? "Saeed"}
            </p>
            <Button
              className={"ms-auto"}
              variant={"ghost"}
              onClick={() => removeCoupon()}
              disabled={isMutating}
            >
              <X className="size-6" />
            </Button>
          </div>
          <p className="text-sm text-gray mt-2">
            {cart?.cart?.coupon?.description}
          </p>
        </div>
      ) : (
        <>
          <Input
            placeholder={t("couponCode")}
            value={couponInputValue}
            onChange={(e) => {
              setCouponInputValue(e.target.value);
            }}
            endIcon={
              <Button
                disabled={isMutating}
                onClick={() => {
                  if (!!couponInputValue.trim()) {
                    applyCoupon(couponInputValue);
                  }
                }}
              >
                {isMutating ? <Spinner /> : t("apply")}
              </Button>
            }
          />
          <FieldError className="mt-2">{applyCouponErr?.message}</FieldError>
        </>
      )}
      <div className="my-3 border-t border-border border-dashed -mx-4" />
      <CouponsListModal
        trigger={
          <div className="flex gap-2 items-center cursor-pointer">
            <div className="w-8 h-8 grid place-items-center rounded-full bg-light-blue text-blue">
              <BadgePercentIcon size={"19px"} />
            </div>
            <p className="text-light capitalize">{t("viewAvailableOffers")}</p>
            {locale === "ar" ? (
              <ChevronLeft className="ms-auto" />
            ) : (
              <ChevronRight className="ms-auto" />
            )}
          </div>
        }
      />
    </div>
  );
}
