"use client";
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  CopyIcon,
  PercentCircleIcon,
  TicketPercent,
} from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";
import toast from "react-hot-toast";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "./types";
import useLocale from "@/src/hooks/use-locale";
import CouponDetailsDialog from "./dialogs/coupon-details-dialog";
import { Coupon } from "./types/product-details";

export default function CouponsSlide({
  coupons,
}: {
  coupons: IProductDetails["coupons"];
}) {
  const t = useTranslations("productView");
  const locale = useLocale();
  return (
    <>
      <h5 className="md:text-gray font-bold text-sm md:text-base mb-2 md:mb-3">
        {t("coupons")}
      </h5>
      <Swiper
        modules={[Navigation, FreeMode]}
        navigation
        freeMode={true}
        slidesPerView={"auto"}
        spaceBetween={6}
        className="pe-8!"
      >
        {coupons.map((coupon) => (
          <SwiperSlide key={coupon.id} className="w-fit!">
            <SmallScreenCouponSlide coupon={coupon} locale={locale} />
            <div className="hidden md:flex items-center p-2 border text-sm border-border rounded-md gap-1.5 bg-[linear-gradient(90deg,#fff_35%,#effdf2_100%)]">
              <span className="min-w-9 h-9 grid rounded-full bg-light-green text-green place-items-center">
                <TicketPercent className="w-1/2 h-1/2" />
              </span>
              <p className="font-semibold text-xs md:text-sm">
                {coupon.title[locale]}
              </p>
              <CouponDetailsDialog
                trigger={
                  <button className="font-thin underline  decoration-dotted ms-1 cursor-pointer">
                    {t("learnMore")}
                  </button>
                }
                coupon={coupon}
              />

              <div
                className="ms-auto px-2 py-1 border rounded-sm border-dashed gap-2 flex items-center cursor-pointer"
                onClick={() => {
                  navigator.clipboard.writeText(coupon.code).then(() => {
                    toast.success(t("couponCopied"));
                  });
                }}
              >
                <p className="text-green uppercase font-bold tracking-wider">
                  {coupon.code}
                </p>
                <CopyIcon className="w-4 h-4" />
              </div>
            </div>
          </SwiperSlide>
        ))}
      </Swiper>
    </>
  );
}

const SmallScreenCouponSlide = ({
  coupon,
  locale,
}: {
  coupon: Coupon;
  locale: "en" | "ar";
}) => (
  <div className="md:hidden">
    <CouponDetailsDialog
      coupon={coupon}
      trigger={
        <div className="flex gap-2 items-center bg-[linear-gradient(90deg,#fff_35%,#effdf2_100%)] border border-dashed border-green-2 rounded-md p-2">
          <PercentCircleIcon className="size-5 text-white fill-green-2" />
          <p className="me-2 text-sm line-clamp-1">
            {coupon.name}, CODE: {coupon.code}
          </p>
          {locale === "ar" ? (
            <ChevronLeftIcon className="size-4" />
          ) : (
            <ChevronRightIcon className="size-4" />
          )}
        </div>
      }
    />
  </div>
);
