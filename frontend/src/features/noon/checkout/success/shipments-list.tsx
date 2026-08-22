"use client";

import React from "react";
import { useTranslations } from "next-intl";
import { Badge } from "@/src/components/ui/badge";
import { Package, Store, Truck, Zap } from "lucide-react";
import { SubOrder } from "../types/place-order.type";
import Price from "@/src/components/shared/Price";
import { centsToAmount } from "@/src/lib/utils";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

interface Props {
  subOrders: SubOrder[];
  currency?: CurrencyCode;
}

export default function ShipmentsList({ subOrders, currency }: Props) {
  const t = useTranslations("checkoutSuccess");

  if (!subOrders || subOrders.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-xl font-bold text-[#404553] flex items-center gap-2">
        <Package className="size-5 text-gray-600" />
        <span>{t("shipmentDetails")}</span>
        <span className="text-sm font-normal text-gray-500">
          ({subOrders.length})
        </span>
      </h2>

      <div className="space-y-4">
        {subOrders.map((subOrder, index) => (
          <ShipmentCard
            key={subOrder.sub_order_number || index}
            subOrder={subOrder}
            index={index}
            totalShipments={subOrders.length}
            currency={currency}
          />
        ))}
      </div>
    </div>
  );
}

function ShipmentCard({
  subOrder,
  index,
  totalShipments,
  currency,
}: {
  subOrder: SubOrder;
  index: number;
  totalShipments: number;
  currency?: CurrencyCode;
}) {
  const t = useTranslations("checkoutSuccess");

  const isExpress =
    subOrder.fulfillment_model?.toLowerCase().includes("express") ||
    subOrder.vendor?.toLowerCase().includes("noon");

  return (
    <div className="bg-white rounded-2xl border border-border shadow-xs overflow-hidden">
      {/* Shipment Header */}
      <div className="bg-gray-50/80 px-5 py-3.5 border-b border-border flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-3">
          <span className="text-sm font-bold text-gray-900">
            {t("shipmentCount", {
              current: index + 1,
              total: totalShipments,
            })}
          </span>
          <span className="text-xs font-mono text-gray-500 bg-white px-2 py-0.5 rounded border border-gray-200">
            #{subOrder.sub_order_number}
          </span>
        </div>

        <div className="flex items-center gap-2">
          {isExpress ? (
            <span className="inline-flex items-center gap-1 bg-[#feee00] text-black text-xs font-extrabold px-2.5 py-0.5 rounded-full shadow-2xs">
              <Zap className="size-3 fill-black" />
              {t("noonExpress")}
            </span>
          ) : (
            <Badge variant="gray" className="text-xs font-semibold">
              <Truck className="size-3 mr-1" />
              {subOrder.fulfillment_model || t("standardDelivery")}
            </Badge>
          )}

          <Badge variant="blue" className="capitalize text-xs">
            {subOrder.status || "Placed"}
          </Badge>
        </div>
      </div>

      {/* Vendor & Delivery Info Bar */}
      <div className="px-5 py-2.5 bg-gray-50/30 border-b border-border/60 flex flex-wrap items-center justify-between text-xs text-gray-600 gap-2">
        <div className="flex items-center gap-1.5">
          <Store className="size-3.5 text-gray-400" />
          <span>{t("fulfilledBy")}:</span>
          <span className="font-semibold text-gray-800">
            {subOrder.vendor || "noon"}
          </span>
        </div>

        <div className="flex items-center gap-1">
          <span>{t("deliveryFee")}:</span>
          <span className="font-bold text-emerald-600">
            {subOrder.is_free_delivery || subOrder.delivery_fee === 0 ? (
              t("freeDelivery")
            ) : (
              <Price
                currentPrice={
                  subOrder.delivery_fee > 100
                    ? centsToAmount(subOrder.delivery_fee)
                    : subOrder.delivery_fee
                }
                size="xs"
                currency={currency}
              />
            )}
          </span>
        </div>
      </div>

      {/* Items List */}
      <div className="divide-y divide-border/60 p-2 sm:p-4">
        {subOrder.items.map((item, itemIdx) => {
          // Normalize prices: if the API returns cents (> 100 typically compared to item value)
          // or standard amount
          const isCents = item.unit_price >= 100 && item.unit_price % 1 === 0;
          const unitPrice = isCents
            ? centsToAmount(item.unit_price)
            : item.unit_price;
          const lineTotal = isCents
            ? centsToAmount(item.line_total)
            : item.line_total;

          return (
            <div
              key={item.listing_ref || item.sku || itemIdx}
              className="py-3 px-3 sm:px-4 flex items-start justify-between gap-4 hover:bg-gray-50/50 rounded-xl transition-colors"
            >
              <div className="flex items-start gap-3 flex-1 min-w-0">
                {/* Item Placeholder Box */}
                <div className="size-14 sm:size-16 rounded-xl bg-gray-100 border border-border flex items-center justify-center shrink-0 relative overflow-hidden">
                  <Package className="size-7 text-gray-400" />
                  <span className="absolute bottom-0 right-0 bg-gray-800 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-tl-md">
                    x{item.quantity}
                  </span>
                </div>

                <div className="flex-1 min-w-0">
                  <h4 className="text-sm font-semibold text-gray-900 line-clamp-2 leading-snug">
                    {item.name_en}
                  </h4>
                  <div className="mt-1 flex items-center gap-2 text-xs text-gray-500 font-mono">
                    <span>SKU: {item.sku || item.listing_ref}</span>
                  </div>
                  <div className="mt-1.5 flex items-center gap-2 text-xs text-gray-600 sm:hidden">
                    <span>
                      {t("quantity")}: {item.quantity}
                    </span>
                    <span>•</span>
                    <Price
                      currentPrice={unitPrice}
                      size="xs"
                      currency={currency}
                    />
                  </div>
                </div>
              </div>

              {/* Price Column */}
              <div className="text-right shrink-0">
                <Price currentPrice={lineTotal} size="sm" currency={currency} />
                {item.quantity > 1 && (
                  <p className="text-[11px] text-gray-500 mt-0.5">
                    {item.quantity} x {unitPrice}
                  </p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
