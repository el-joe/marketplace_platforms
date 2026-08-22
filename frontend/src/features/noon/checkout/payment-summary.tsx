import Price from "@/src/components/shared/Price";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";
import { useTranslations } from "next-intl";

export default function PaymentSummary({
  checkoutSummary,
}: {
  checkoutSummary: IPrepareCheckout["order_summary"] & {
    item_count: number;
  };
}) {
  const t = useTranslations("checkout");
  return (
    <div className="p-4 bg-white rounded-[16px]">
      <div className="flex gap-2 items-center mb-4">
        <h3 className="font-bold">{t("paymentSummary")}</h3>
        <p className="py-1 px-2 rounded-full bg-gray-2 text-gray text-xs">
          {checkoutSummary?.item_count} {t("items")}
          {/* {checkoutSummary?.item_count} {t("items")} */}
        </p>
      </div>
      {/* subtotal */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("subtotal")}</p>
        <Price
          currentPrice={checkoutSummary.subtotal}
          oldPrice={checkoutSummary.discount}
          size="xs"
        />
      </div>
      {/* shipping fee */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("shippingFee")}</p>
        <p className="text-gray">{checkoutSummary.shipping}</p>
      </div>
      {/* tax */}
      <div className="flex justify-between pb-4 border-b border-border border-dashed mb-4">
        <p className=" text-gray">{t("tax")}</p>
        <p className="text-gray">{checkoutSummary.tax}</p>
      </div>
      {/* total */}
      <div className="flex justify-between">
        <p className=" text-lg font-bold">{t("total")}</p>
        <Price currentPrice={checkoutSummary.total} />
      </div>
    </div>
  );
}
