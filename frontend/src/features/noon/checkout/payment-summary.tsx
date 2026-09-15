import Price from "@/src/components/shared/Price";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";
import { useTranslations } from "next-intl";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export default function PaymentSummary({
  checkoutSummary,
}: {
  checkoutSummary: IPrepareCheckout["order_summary"] & {
    item_count: number;
  };
}) {
  const t = useTranslations("checkout");
  const currency = checkoutSummary.currency as CurrencyCode;

  return (
    <div className="p-4 bg-white rounded-[16px]">
      <div className="flex gap-2 items-center mb-4">
        <h3 className="font-bold">{t("paymentSummary")}</h3>
        <p className="py-1 px-2 rounded-full bg-gray-2 text-gray text-xs">
          {checkoutSummary?.item_count} {t("items")}
        </p>
      </div>
      {/* subtotal */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("subtotal")}</p>
        <Price
          currentPrice={checkoutSummary.subtotal}
          size="xs"
          currency={currency}
        />
      </div>
      {/* discount */}
      {checkoutSummary.discount > 0 && (
        <div className="flex justify-between mb-3">
          <p className=" text-gray">{t("discount")}</p>
          <p className="text-green-600">
            -
            <Price
              currentPrice={checkoutSummary.discount}
              size="xs"
              currency={currency}
              className="inline text-green-600"
            />
          </p>
        </div>
      )}
      {/* shipping fee */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("shippingFee")}</p>
        {checkoutSummary.shipping > 0 ? (
          <Price
            currentPrice={checkoutSummary.shipping}
            size="xs"
            currency={currency}
          />
        ) : (
          <p className="text-gray">{t("free")}</p>
        )}
      </div>
      {/* cod fee */}
      {checkoutSummary.cod_fee > 0 && (
        <div className="flex justify-between mb-3">
          <p className=" text-gray">{t("codFee")}</p>
          <Price
            currentPrice={checkoutSummary.cod_fee}
            size="xs"
            currency={currency}
          />
        </div>
      )}
      {/* warranty */}
      {checkoutSummary.warranty_total > 0 && (
        <div className="flex justify-between mb-3">
          <p className=" text-gray">{t("warrantyTotal")}</p>
          <Price
            currentPrice={checkoutSummary.warranty_total}
            size="xs"
            currency={currency}
          />
        </div>
      )}
      {/* gift card */}
      {checkoutSummary.gift_card_applied > 0 && (
        <div className="flex justify-between mb-3">
          <p className=" text-gray">{t("giftCard")}</p>
          <p className="text-green-600">
            -
            <Price
              currentPrice={checkoutSummary.gift_card_applied}
              size="xs"
              currency={currency}
              className="inline text-green-600"
            />
          </p>
        </div>
      )}
      {/* tax */}
      <div className="flex justify-between pb-4 border-b border-border border-dashed mb-4">
        <p className=" text-gray">{t("tax")}</p>
        <Price currentPrice={checkoutSummary.tax} size="xs" currency={currency} />
      </div>
      {/* total */}
      <div className="flex justify-between">
        <p className=" text-lg font-bold">{t("total")}</p>
        <Price currentPrice={checkoutSummary.total} currency={currency} />
      </div>
    </div>
  );
}
