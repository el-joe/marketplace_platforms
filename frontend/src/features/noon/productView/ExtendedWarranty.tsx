"use client";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { ArrowBigRight, ChevronLeft, ChevronRight } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "./types";
import { centsToAmount } from "@/src/lib/utils";

const warranties = [
  {
    id: 1,
    duration: "1 year",
    image:
      "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/extended_warranty_v4.png",
    title: "Extended Warranty Plan",
    coreBenefits: [
      "Active after brand warranty",
      "Covers electrical & mechanical failures",
      "Get unlimited claims",
    ],
    cost: 172,
  },
  {
    id: 2,
    duration: "6 months",
    image:
      "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/extended_warranty_v4.png",
    title: "Extended Warranty Plan",
    coreBenefits: [
      "Active after brand warranty",
      "Covers electrical & mechanical failures",
      "Get unlimited claims",
    ],
    cost: 172,
  },
  {
    id: 3,
    duration: "1 year",
    image:
      "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/accidental_warranty_v5.png",
    title: "Extended Warranty Plan",
    coreBenefits: [
      "Active after brand warranty",
      "Covers electrical & mechanical failures",
      "Get unlimited claims",
    ],
    cost: 172,
  },
  {
    id: 4,
    duration: "1 year",
    image:
      "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/accidental_extended_warranty_v4.png",
    title: "Extended Warranty Plan",
    coreBenefits: [
      "Active after brand warranty",
      "Covers electrical & mechanical failures",
      "Get unlimited claims",
    ],
    cost: 172,
  },
];

export default function ExtendedWarranty({
  warrantiesData,
}: {
  warrantiesData: IProductDetails["warranty_plans"];
}) {
  const t = useTranslations("productView");
  const locale = useLocale();
  return (
    <>
      <h5 className="text-gray font-semibold mb-3">
        {t("getExtendedWarranty")}
      </h5>
      <Swiper
        modules={[Navigation, FreeMode]}
        navigation
        freeMode={true}
        slidesPerView={1.1}
        spaceBetween={14}
        breakpoints={{
          1280: { slidesPerView: 1.6 },
        }}
        className="pe-8!"
      >
        {warrantiesData.map((warranty) => (
          <SwiperSlide key={warranty.id} className="">
            <div
              className={`p-3 rounded-md border border-border cursor-pointer transition-all hover:border-black`}
            >
              <div className="flex gap-2 items-center">
                <Image
                  src={
                    "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/extended_warranty_v4.png"
                  }
                  alt="warranty icon"
                  width={47}
                  height={47}
                  className="rounded-md"
                />
                <div className="flex-1">
                  <p className="px-2 bg-light-blue text-blue text-sm w-fit mb-1">
                    {warranty.duration_label}
                  </p>
                  <h4 className="font-semibold text:base lg:text-lg flex items-center">
                    {warranty.name}
                    {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
                  </h4>
                </div>
              </div>
              <ul className="mt-3 gap-1 flex flex-col">
                {warranty.features.map((benefit, i) => (
                  <li
                    key={i}
                    className="text-xs lg:text-sm text-gray flex items-center"
                  >
                    <ArrowBigRight />
                    {benefit}
                  </li>
                ))}
              </ul>
              <div className="flex justify-between items-center">
                <Price
                  currentPrice={centsToAmount(warranty.price)}
                  currency={warranty.currency}
                />
                <Button
                  variant={"outline"}
                  className={
                    "bg-transparent! px-12 py-2 text-lg text-blue font-bold border-blue"
                  }
                >
                  {t("select")}
                </Button>
              </div>
            </div>
          </SwiperSlide>
        ))}
      </Swiper>
    </>
  );
}
