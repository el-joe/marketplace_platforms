"use client";

import React from "react";
import { useTranslations } from "next-intl";
import { CreditCard, Receipt, ShieldCheck } from "lucide-react";
import Price from "@/src/components/shared/Price";
import { IPlaceOrderResponse } from "../types/place-order.type";

interface Props {
  order: IPlaceOrderResponse;
}

export default function OrderSummaryCard({ order }: Props) {
  const t = useTranslations("checkoutSuccess");

  // Compute item count and subtotal
  const allItems = order.sub_orders?.flatMap((so) => so.items) || [];
  const itemCount = allItems.reduce((acc, it) => acc + (it.quantity || 1), 0);

  const rawSubtotal = allItems.reduce(
    (acc, it) => acc + (it.line_total || it.unit_price * it.quantity || 0),
    0,
  );

  const itemsSubtotal = rawSubtotal ?? 0;

  const totalDeliveryFee = (order.sub_orders || []).reduce((acc, so) => {
    if (so.is_free_delivery) return acc;
    return acc + (so.delivery_fee || 0);
  }, 0);

  const rawTotal = order.total || rawSubtotal + totalDeliveryFee;
  const grandTotal = rawTotal ?? 0;

  const warrantyTotal = order.warranty_total ?? 0;

  return (
    <div className="bg-white rounded-2xl p-6 border border-border shadow-xs flex flex-col gap-5">
      <div className="flex items-center gap-2 border-b border-border pb-3">
        <Receipt className="size-5 text-gray-700" />
        <h3 className="font-bold text-lg text-[#404553]">
          {t("orderSummary")}
        </h3>
      </div>

      {/* Line items */}
      <div className="space-y-3 text-sm">
        <div className="flex items-center justify-between text-gray-600">
          <span>
            {t("itemsSubtotal")}{" "}
            <span className="text-xs text-gray-400">({itemCount})</span>
          </span>
          <Price
            currentPrice={itemsSubtotal}
            size="xs"
            currency={order.currency}
          />
        </div>

        <div className="flex items-center justify-between text-gray-600">
          <span>{t("shipping")}</span>
          <span className="font-medium text-emerald-600">
            {totalDeliveryFee === 0 ? (
              t("freeDelivery")
            ) : (
              <Price
                currentPrice={totalDeliveryFee}
                size="xs"
                currency={order.currency}
              />
            )}
          </span>
        </div>

        {warrantyTotal > 0 && (
          <div className="flex items-center justify-between text-gray-600">
            <span className="flex items-center gap-1">
              <ShieldCheck className="size-3.5 text-blue-600" />
              {t("warrantyTotal")}
            </span>
            <Price
              currentPrice={warrantyTotal}
              size="xs"
              currency={order.currency}
            />
          </div>
        )}
      </div>

      {/* Grand Total */}
      <div className="border-t border-border pt-4 flex items-baseline justify-between">
        <div>
          <span className="font-bold text-base text-gray-900">
            {t("total")}
          </span>
          <p className="text-[11px] text-gray-500 font-normal">
            ({t("incVat")})
          </p>
        </div>
        <Price
          currentPrice={grandTotal}
          size="lg"
          currency={order.currency}
          className="text-gray-900"
        />
      </div>

      {/* Payment details pill */}
      <div className="bg-gray-50 rounded-xl p-3 border border-gray-200/80 text-xs text-gray-600 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <CreditCard className="size-4 text-gray-500" />
          <span>{t("paymentStatus")}:</span>
        </div>
        <span className="font-bold capitalize text-gray-800">
          {order.payment_status || t("paid")}
        </span>
      </div>
    </div>
  );
}
