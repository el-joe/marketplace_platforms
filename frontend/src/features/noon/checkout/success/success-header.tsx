"use client";

import React, { useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Check, CheckCircle2, Copy, ShoppingBag, Truck } from "lucide-react";
import { Badge } from "@/src/components/ui/badge";
import { Button } from "@/src/components/ui/button";
import { IPlaceOrderResponse } from "../types/place-order.type";
import useLocale from "@/src/hooks/use-locale";

interface Props {
  order: IPlaceOrderResponse;
}

export default function SuccessHeader({ order }: Props) {
  const t = useTranslations("checkoutSuccess");
  const locale = useLocale();
  const [copied, setCopied] = useState(false);

  const handleCopyOrderNumber = async () => {
    if (!order.order_number) return;
    try {
      await navigator.clipboard.writeText(order.order_number);
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    } catch (err) {
      console.error("Failed to copy order number:", err);
    }
  };

  const formattedDate = order.placed_at
    ? new Date(order.placed_at).toLocaleDateString(
        locale === "ar" ? "ar-EG" : "en-US",
        {
          year: "numeric",
          month: "short",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        },
      )
    : new Date().toLocaleDateString(locale === "ar" ? "ar-EG" : "en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });

  const isPaid =
    order.payment_status?.toLowerCase() === "paid" ||
    order.payment_status?.toLowerCase() === "completed";

  return (
    <div className="bg-white rounded-2xl p-6 md:p-8 border border-border shadow-xs">
      <div className="flex flex-col items-center text-center">
        {/* Animated Checkmark Circle */}
        <div className="relative mb-4">
          <div className="size-20 md:size-24 rounded-full bg-emerald-50 border-4 border-emerald-100 flex items-center justify-center text-emerald-600 shadow-inner animate-in zoom-in-50 duration-300">
            <CheckCircle2 className="size-12 md:size-14 text-emerald-600" />
          </div>
          <div className="absolute -inset-1 rounded-full bg-emerald-400/20 animate-ping -z-10" />
        </div>

        {/* Heading */}
        <h1 className="text-2xl md:text-3xl font-extrabold text-[#404553] tracking-tight">
          {t("orderPlacedSuccessfully")}
        </h1>
        <p className="mt-2 text-sm md:text-base text-gray-500 max-w-xl">
          {t("thankYouMessage")}
        </p>

        {/* Order Details Chip / Bar */}
        <div className="mt-6 w-full max-w-2xl bg-gray-50 rounded-xl p-4 border border-gray-200/80 flex flex-wrap items-center justify-between gap-3 text-sm">
          <div className="flex items-center gap-2">
            <span className="text-gray-500 font-medium">
              {t("orderNumber")}:
            </span>
            <span className="font-bold text-gray-900 tracking-wide font-mono">
              #{order.order_number}
            </span>
            <button
              onClick={handleCopyOrderNumber}
              type="button"
              className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded-md transition-colors cursor-pointer"
              title={t("copyOrderNumber")}
            >
              {copied ? (
                <>
                  <Check className="size-3.5 text-emerald-600" />
                  <span className="text-emerald-600 font-medium">
                    {t("copied")}
                  </span>
                </>
              ) : (
                <>
                  <Copy className="size-3.5" />
                  <span>{t("copyOrderNumber")}</span>
                </>
              )}
            </button>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-gray-500">{t("placedOn")}:</span>
            <span className="font-semibold text-gray-800">{formattedDate}</span>
          </div>

          <div className="flex items-center gap-2">
            <Badge variant={isPaid ? "green" : "yellow"} className="capitalize">
              {order.payment_status || (isPaid ? t("paid") : t("pending"))}
            </Badge>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="mt-6 flex flex-wrap items-center justify-center gap-3 w-full max-w-md">
          <Link
            href={`/orders/${order.order_number}`}
            className="flex-1 min-w-40"
          >
            <Button className="w-full bg-[#3866df] hover:bg-[#2d52b5] text-white font-bold h-12 rounded-xl text-base flex items-center justify-center gap-2 shadow-sm transition-all">
              <Truck className="size-5" />
              {t("trackOrder")}
            </Button>
          </Link>
          <Link href="/" className="flex-1 min-w-40">
            <Button
              variant="outline"
              className="w-full border-gray-300 hover:bg-gray-50 text-gray-800 font-semibold h-12 rounded-xl text-base flex items-center justify-center gap-2"
            >
              <ShoppingBag className="size-5 text-gray-600" />
              {t("continueShopping")}
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
}
