"use client";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import {
  ArrowBigRight,
  ChevronLeft,
  ChevronRight,
  ShoppingCartIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React, { useState } from "react";
import { FreeMode, Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import useLocale from "@/src/hooks/use-locale";
import { IProductDetails } from "./types";
import { Warranty } from "./types/product-details";
import ExtendedWarrantySheet from "./dialogs/extended-warranty-sheet";
import { useExtendedWarrantyActions } from "./helpers/use-extended-warranty-actions";
import { Spinner } from "@/src/components/ui/spinner";

const FALLBACK_WARRANTY_IMAGE =
  "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/extended_warranty_v4.png";

export default function ExtendedWarranty({
  warrantiesData,
  listingId,
}: {
  warrantiesData: IProductDetails["warranty_plans"];
  listingId: string;
}) {
  const t = useTranslations("productView");
  const locale = useLocale();
  const {
    activePlanId: selectedPlanId,
    togglePlan: selectPlan,
    isPlanPending,
    isMutating,
    isInCart,
  } = useExtendedWarrantyActions(listingId);
  const [activeWarrantyForSheet, setActiveWarrantyForSheet] =
    useState<Warranty | null>(null);


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
          1280: { slidesPerView: 1.3 },
        }}
        className="pe-8!"
      >
        {warrantiesData.map((warranty) => (
          <SwiperSlide key={warranty.id} className="h-auto!">
            <div
              onClick={() => setActiveWarrantyForSheet(warranty)}
              className={`p-3 rounded-md border cursor-pointer transition-all hover:border-black h-full flex flex-col ${
                selectedPlanId === warranty.id
                  ? "border-blue-3"
                  : "border-border"
              }`}
            >
              <div className="flex gap-2 items-center">
                <Image
                  src={warranty.image_url || FALLBACK_WARRANTY_IMAGE}
                  alt="warranty icon"
                  width={47}
                  height={47}
                  className="rounded-md"
                />
                <div className="flex-1">
                  <p className="px-2 bg-light-blue text-blue text-sm w-fit mb-1">
                    {warranty.duration_label[locale]}
                  </p>
                  <h4 className="font-semibold text:base lg:text-lg flex items-center">
                    {warranty.name[locale]}
                    {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
                  </h4>
                </div>
              </div>
              <ul className="mt-3 gap-1 flex flex-col">
                {(warranty.features[locale] ?? []).map((benefit, i) => (
                  <li
                    key={i}
                    className="text-xs lg:text-sm text-gray flex items-start"
                  >
                    <ArrowBigRight />
                    {benefit}
                  </li>
                ))}
              </ul>
              {isInCart && selectedPlanId === warranty.id && (
                <p className="mt-3 flex items-center gap-1.5 rounded-lg bg-light-green px-2.5 py-1.5 text-xs font-semibold text-green">
                  <ShoppingCartIcon size={14} />
                  {t("warrantyAddedToCart")}
                </p>
              )}
              <div className="flex justify-between items-center mt-auto pt-3">
                <Price
                  currentPrice={warranty.price}
                  currency={warranty.currency}
                />
                <Button
                  variant={
                    selectedPlanId === warranty.id ? "default" : "outline"
                  }
                  disabled={isMutating}
                  onClick={(e) => {
                    e.stopPropagation();
                    selectPlan(warranty.id);
                  }}
                  className={
                    selectedPlanId === warranty.id
                      ? "px-12 py-2 text-lg font-bold bg-blue-3 text-white border-blue-3"
                      : "bg-transparent! px-12 py-2 text-lg text-blue font-bold border-blue"
                  }
                >
                  {isPlanPending(warranty.id) ? (
                    <Spinner />
                  ) : selectedPlanId === warranty.id ? (
                    t("selected")
                  ) : (
                    t("select")
                  )}
                </Button>
              </div>
            </div>
          </SwiperSlide>
        ))}
      </Swiper>

      <ExtendedWarrantySheet
        open={Boolean(activeWarrantyForSheet)}
        onOpenChange={(open) => {
          if (!open) setActiveWarrantyForSheet(null);
        }}
        warranty={activeWarrantyForSheet}
        isSelected={selectedPlanId === activeWarrantyForSheet?.id}
        onSelect={() => {
          if (activeWarrantyForSheet) {
            selectPlan(activeWarrantyForSheet.id);
          }
        }}
      />
    </>
  );
}
