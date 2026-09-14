"use client";

import React, { useEffect, useState } from "react";
import Image from "next/image";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import { Info, X } from "lucide-react";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetTitle,
} from "@/src/components/ui/sheet";
import { Button } from "@/src/components/ui/button";
import Price from "@/src/components/shared/Price";
import { Warranty } from "../types/product-details";

const FALLBACK_WARRANTY_IMAGE =
  "https://f.nooncdn.com/noon-cdn/s/app/com/noon/images/external-warranty/images/extended_warranty_v4.png";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  warranty: Warranty | null;
  isSelected?: boolean;
  onSelect?: () => void;
};

export default function ExtendedWarrantySheet({
  open,
  onOpenChange,
  warranty,
  isSelected = false,
  onSelect,
}: Props) {
  const t = useTranslations("productView");

  // Keep displayed warranty during exit animation
  const [cachedWarranty, setCachedWarranty] = useState<Warranty | null>(
    warranty,
  );

  useEffect(() => {
    if (warranty) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setCachedWarranty(warranty);
    }
  }, [warranty]);

  const activeWarranty = warranty || cachedWarranty;

  if (!activeWarranty) return null;

  const claimSteps = [
    t("claimStep1"),
    t("claimStep2"),
    t("claimStep3"),
    t("claimStep4"),
    t("claimStep5"),
  ];

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        className="w-full sm:max-w-[480px]! p-0 flex flex-col h-full overflow-hidden bg-white gap-0 border-s border-border"
        showCloseButton={false}
        initialFocus={false}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 sm:p-5 border-b border-border bg-white shrink-0">
          <div className="flex items-center gap-3">
            <Image
              src={activeWarranty.image_url || FALLBACK_WARRANTY_IMAGE}
              alt={activeWarranty.name}
              width={46}
              height={46}
              className="rounded-lg object-contain shrink-0"
            />
            <div className="flex flex-col min-w-0">
              <span className="text-xs font-bold text-blue tracking-wider uppercase leading-none mb-1">
                {activeWarranty.duration_label}
              </span>
              <SheetTitle className="text-base sm:text-lg font-bold text-gray-900 leading-tight">
                {activeWarranty.name}
              </SheetTitle>
            </div>
          </div>

          <SheetClose
            render={
              <button
                type="button"
                className="p-1.5 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors cursor-pointer"
                aria-label="Close"
              >
                <X className="w-5 h-5" />
              </button>
            }
          />
        </div>

        {/* Scrollable Body */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 bg-[#f8f9fa]">
          {/* Card 1: Coverage & Policies */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-5 space-y-4 shadow-2xs">
            {/* Coverage */}
            <div className="space-y-1.5">
              <h4 className="font-bold text-sm text-gray-900">
                {t("warrantyCoverage")}
              </h4>
              <ul className="list-disc list-outside ms-4 space-y-1 text-xs sm:text-[13px] text-gray-600 leading-relaxed marker:text-gray-400">
                <li>{t("warrantyCoverageDesc1")}</li>
                <li>{t("warrantyCoverageDesc2")}</li>
              </ul>
            </div>

            {/* Key Benefits */}
            <div className="space-y-1.5">
              <h4 className="font-bold text-sm text-gray-900">
                {t("warrantyKeyBenefits")}
              </h4>
              <ul className="list-disc list-outside ms-4 space-y-1 text-xs sm:text-[13px] text-gray-600 leading-relaxed marker:text-gray-400">
                <li>{t("warrantyBenefit1")}</li>
                <li>{t("warrantyBenefit2")}</li>
                <li>{t("warrantyBenefit3")}</li>
              </ul>
            </div>

            {/* Delivery */}
            <div className="space-y-1.5">
              <h4 className="font-bold text-sm text-gray-900">
                {t("warrantyDelivery")}
              </h4>
              <ul className="list-disc list-outside ms-4 space-y-1 text-xs sm:text-[13px] text-gray-600 leading-relaxed marker:text-gray-400">
                <li>{t("warrantyDeliveryDesc1")}</li>
              </ul>
            </div>

            {/* Fulfilment */}
            <div className="space-y-1.5">
              <h4 className="font-bold text-sm text-gray-900">
                {t("warrantyFulfilment")}
              </h4>
              <ul className="list-disc list-outside ms-4 space-y-1 text-xs sm:text-[13px] text-gray-600 leading-relaxed marker:text-gray-400">
                <li>{t("warrantyFulfilmentDesc1")}</li>
              </ul>
            </div>

            {/* Cancellation/Refund & Return */}
            <div className="space-y-1.5">
              <h4 className="font-bold text-sm text-gray-900">
                {t("warrantyCancellationRefund")}
              </h4>
              <ul className="list-disc list-outside ms-4 space-y-1 text-xs sm:text-[13px] text-gray-600 leading-relaxed marker:text-gray-400">
                <li>{t("warrantyCancellationDesc1")}</li>
              </ul>
            </div>
          </div>

          {/* Card 2: Claims Stepper */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-5 space-y-5 shadow-2xs">
            <h3 className="text-base sm:text-[17px] font-bold text-gray-900 leading-snug">
              {t("claimsZeroHassles")}{" "}
              <span className="text-red font-bold">{t("quick")}</span>{" "}
              {t("resolutions")}
            </h3>

            {/* Stepper */}
            <div className="flex flex-col space-y-4 pt-1">
              {claimSteps.map((step, idx) => (
                <div key={idx} className="relative flex items-center gap-3">
                  <div className="relative flex items-center justify-center shrink-0 w-2.5 h-2.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-gray-400 z-10 shrink-0" />
                    {idx < claimSteps.length - 1 && (
                      <span className="absolute top-2.5 bottom-[-22px] w-[1.5px] bg-gray-300" />
                    )}
                  </div>
                  <span className="text-xs sm:text-[13px] text-gray-600 font-medium leading-normal">
                    {step}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Footnote Notice */}
          <div className="flex items-start gap-2 text-xs text-gray-500 px-1 pt-1 leading-relaxed">
            <Info className="w-4 h-4 shrink-0 mt-0.5 text-gray-400" />
            <span>
              {t("warrantyTermsNotice")}{" "}
              <Link
                href="/terms"
                target="_blank"
                className="text-blue-600 font-medium underline hover:text-blue-700"
              >
                {t("termsAndConditions")}
              </Link>
            </span>
          </div>
        </div>

        {/* Sticky Footer */}
        <div className="border-t border-border bg-white p-4 sm:px-6 flex items-center justify-between gap-4 shrink-0 shadow-lg">
          <Price
            currentPrice={activeWarranty.price}
            currency={activeWarranty.currency}
            size="lg"
            className="text-xl sm:text-2xl font-bold text-gray-900"
          />

          <Button
            variant={isSelected ? "default" : "outline"}
            onClick={onSelect}
            className={
              isSelected
                ? "px-10 py-2.5 text-base font-bold bg-blue-3 text-white border-blue-3 hover:bg-blue-3/90 uppercase rounded-xl min-w-36"
                : "px-10 py-2.5 text-base font-bold border-blue text-blue hover:bg-blue-50 bg-transparent uppercase rounded-xl min-w-36"
            }
          >
            {isSelected ? t("selected") : t("select")}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  );
}
