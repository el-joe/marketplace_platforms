"use client";

import React, { JSXElementConstructor } from "react";
import {
  Dialog,
  DialogContent,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  returnDays?: number;
  policyUrl?: string;
};

function GreenCheckIcon() {
  return (
    <svg
      className="w-[18px] h-[18px] shrink-0 mt-0.5"
      viewBox="0 0 20 20"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle cx="10" cy="10" r="10" fill="#22C55E" />
      <path
        d="M6 10.2L8.6 12.8L14 7.2"
        stroke="white"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function RedCrossIcon() {
  return (
    <svg
      className="w-[18px] h-[18px] shrink-0 mt-0.5"
      viewBox="0 0 20 20"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle cx="10" cy="10" r="10" fill="#EF4444" />
      <path
        d="M7 7L13 13M13 7L7 13"
        stroke="white"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export default function EasyReturnsDialog({
  trigger,
  returnDays = 14,
  policyUrl = "/return-policy",
}: Props) {
  const t = useTranslations("productView");

  const allowedConditions = [
    t("returnCondition1"),
    t("returnCondition2"),
    t("returnCondition3"),
    returnDays
      ? t("returnCondition4", { days: returnDays })
      : t("returnCondition4Default"),
  ];

  const disallowedConditions = [
    t("returnNotAllowed1"),
    t("returnNotAllowed2"),
    t("returnNotAllowed3"),
  ];

  return (
    <Dialog>
      <DialogTrigger render={trigger} />
      <DialogContent className="sm:max-w-[460px] p-6 sm:p-7 pt-7 rounded-2xl flex flex-col gap-5 border border-border shadow-lg">
        {/* Title */}
        <h2 className="text-lg sm:text-xl font-bold text-gray-900 tracking-tight text-start pe-6">
          {t("whichItemsCanBeReturned")}
        </h2>

        {/* Conditions List */}
        <div className="flex flex-col gap-3.5">
          {/* Allowed Items */}
          {allowedConditions.map((condition, index) => (
            <div key={`allowed-${index}`} className="flex items-start gap-3">
              <GreenCheckIcon />
              <p className="text-sm text-gray-700 leading-snug">{condition}</p>
            </div>
          ))}

          {/* Disallowed Items */}
          {disallowedConditions.map((condition, index) => (
            <div key={`disallowed-${index}`} className="flex items-start gap-3">
              <RedCrossIcon />
              <p className="text-sm text-gray-700 leading-snug">{condition}</p>
            </div>
          ))}
        </div>

        {/* Learn More Button */}
        <Link href={policyUrl} target="_blank" className="w-full mt-2 block">
          <Button
            variant="outline"
            className="w-full border-blue-600 text-blue-600 hover:bg-blue-50/70 hover:text-blue-700 font-semibold py-5 rounded-lg text-sm transition-colors cursor-pointer"
          >
            {t("learnMoreAboutReturnPolicy")}
          </Button>
        </Link>
      </DialogContent>
    </Dialog>
  );
}
