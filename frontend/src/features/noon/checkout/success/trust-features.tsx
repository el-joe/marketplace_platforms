"use client";

import React from "react";
import { useTranslations } from "next-intl";
import { Headphones, RotateCcw, ShieldCheck, Truck } from "lucide-react";

export default function TrustFeatures() {
  const t = useTranslations("checkoutSuccess");

  const features = [
    {
      icon: Truck,
      title: t("fastDeliveryTitle"),
      desc: t("fastDeliveryDesc"),
      iconColor: "text-blue-600 bg-blue-50",
    },
    {
      icon: ShieldCheck,
      title: t("genuineTitle"),
      desc: t("genuineDesc"),
      iconColor: "text-emerald-600 bg-emerald-50",
    },
    {
      icon: RotateCcw,
      title: t("easyReturnsTitle"),
      desc: t("easyReturnsDesc"),
      iconColor: "text-amber-600 bg-amber-50",
    },
    {
      icon: Headphones,
      title: t("supportTitle"),
      desc: t("supportDesc"),
      iconColor: "text-purple-600 bg-purple-50",
    },
  ];

  return (
    <div className="bg-white rounded-2xl p-6 border border-border shadow-xs">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {features.map((feature, idx) => {
          const Icon = feature.icon;
          return (
            <div
              key={idx}
              className="flex items-start gap-3 p-3 rounded-xl bg-gray-50/70 border border-gray-100"
            >
              <div className={`p-2.5 rounded-xl shrink-0 ${feature.iconColor}`}>
                <Icon className="size-5" />
              </div>
              <div>
                <h4 className="text-xs font-bold text-gray-900 leading-tight">
                  {feature.title}
                </h4>
                <p className="text-[11px] text-gray-500 mt-0.5 leading-relaxed">
                  {feature.desc}
                </p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
