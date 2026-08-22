"use client";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "./types";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";

type props = {
  paymentsData: IProductDetails["payment_options"];
};

export default function PaymentDiscount({ paymentsData }: props) {
  console.log("paymentsData", paymentsData);
  const t = useTranslations("productView");
  const locale = useLocale();
  return (
    <>
      <h5 className="text-gray font-semibold mb-3">{t("paymentDiscount")}</h5>
      <Swiper
        modules={[Navigation, FreeMode]}
        navigation
        freeMode={true}
        slidesPerView={1.1}
        spaceBetween={6}
        breakpoints={{
          1024: {
            spaceBetween: 12,
          },
          1440: {
            slidesPerView: 1.7,
            spaceBetween: 16,
          },
        }}
        className="pe-8! align-stretch!"
        wrapperClass="align-stretch!"
      >
        {paymentsData.map((option, i) => (
          <SwiperSlide key={i} className="h-auto!">
            <div className="h-full flex items-center p-2 border le text-xs border-border rounded-md gap-1 bg-[linear-gradient(90deg,#ffecd3_35%,#f8ecfd_100%)]">
              <Image
                src={
                  option.provider_logo_path ??
                  "/images/payment-discount-card-1.avif"
                }
                alt={(option.display_name[locale] as string) ?? ""}
                width={35}
                height={35}
              />
              <p className="flex gap-1">
                {option.label}
                <Link
                  href={option?.learn_more_url ?? "#"}
                  className="underline decoration-dotted whitespace-nowrap"
                >
                  {t("learnMore")}
                </Link>
              </p>
            </div>
          </SwiperSlide>
        ))}
      </Swiper>
    </>
  );
}
