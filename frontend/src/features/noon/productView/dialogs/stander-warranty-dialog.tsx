"use client";

import React, { JSXElementConstructor } from "react";
import {
  Dialog,
  DialogContent,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { BadgeCheck, CircleGauge, ExternalLink } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  brandName?: string;
  warrantyYears?: number;
  countryName?: string;
  policyUrl?: string;
};

export default function StanderWarrantyDialog({
  trigger,
  brandName = "Apple",
  warrantyYears = 1,
  countryName,
  policyUrl = "/warranty-policy",
}: Props) {
  const t = useTranslations("productView");

  return (
    <Dialog>
      <DialogTrigger render={trigger} />
      <DialogContent className="sm:max-w-110 p-6 pt-8 rounded-2xl flex flex-col gap-5 border border-border shadow-lg bg-[#f9f9fb]">
        {/* Shield Icon Header */}
        <Image
          src="/images/blue-shield.avif"
          alt="shield"
          width={80}
          height={90}
          className="mx-auto"
        />

        {/* Title with Verified Check Badge */}
        <div className="flex items-center justify-center gap-1.5 -mt-1">
          <h2 className="text-base sm:text-lg font-bold text-gray-900">
            {t("coveredBy", { brand: brandName })}
          </h2>
          <BadgeCheck className="w-5 h-5 text-white fill-blue-600 shrink-0" />
        </div>

        {/* Features Container Card */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 flex flex-col gap-4 sm:gap-5">
          {/* Feature 1: Manufacturer Warranty */}
          <div className="flex items-start gap-3">
            <div className="shrink-0 mt-0.5">
              <svg
                className="w-5 h-5 text-gray-900"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <polygon
                  points="12,7.5 13.2,10.2 16,11.2 13.2,12.2 12,15 10.8,12.2 8,11.2 10.8,10.2"
                  fill="currentColor"
                  stroke="none"
                />
              </svg>
            </div>
            <div className="flex flex-col gap-0.5">
              <h3 className="text-sm font-semibold text-gray-900">
                {warrantyYears > 1
                  ? t("yearManufacturerWarranty", { years: warrantyYears })
                  : t("yearManufacturerWarrantyDefault")}
              </h3>
              <p className="text-xs text-gray-500 leading-relaxed">
                {t("manufacturerWarrantyDesc", { brand: brandName })}
              </p>
            </div>
          </div>

          {/* Feature 2: Geographical Coverage */}
          <div className="flex items-start gap-3">
            <CircleGauge
              className="w-5 h-5 text-gray-900 shrink-0 mt-0.5"
              strokeWidth={1.75}
            />
            <div className="flex flex-col gap-0.5">
              <h3 className="text-sm font-semibold text-gray-900">
                {countryName
                  ? t("validAcrossRegion", { country: countryName })
                  : t("validAcrossRegionDefault")}
              </h3>
              <p className="text-xs text-gray-500 leading-relaxed">
                {t("validAcrossRegionDesc", { brand: brandName })}
              </p>
            </div>
          </div>

          {/* Feature 3: Backed by Noon */}
          <div className="flex items-start gap-3">
            <div className="shrink-0 mt-0.5">
              <svg
                className="w-5 h-5 text-gray-900"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <circle cx="12" cy="11.5" r="3.2" strokeWidth="1.5" />
              </svg>
            </div>
            <div className="flex flex-col gap-0.5">
              <h3 className="text-sm font-semibold text-gray-900">
                {t("backedByNoon")}
              </h3>
              <p className="text-xs text-gray-500 leading-relaxed">
                {t("backedByNoonDesc")}
              </p>
            </div>
          </div>
        </div>

        {/* Noon's Warranty Policy Link Card */}
        <Link
          href={policyUrl}
          target="_blank"
          className="flex items-center justify-between p-3.5 sm:p-4 rounded-xl border border-blue-100 bg-[#f8faff] hover:bg-blue-50/70 transition-colors group"
        >
          <div className="flex flex-col gap-0.5">
            <span className="text-sm font-bold text-blue-600 group-hover:underline">
              {t("noonWarrantyPolicy")}
            </span>
            <span className="text-xs text-gray-500">
              {t("noonWarrantyPolicyDesc")}
            </span>
          </div>
          <ExternalLink className="w-4 h-4 text-blue-600 shrink-0 ms-2" />
        </Link>
      </DialogContent>
    </Dialog>
  );
}
