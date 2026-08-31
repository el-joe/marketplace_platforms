"use client";
import Price from "@/src/components/shared/Price";
import { currencySymbols } from "@/src/helpers/get-currency-symbol";
import { useCartContext } from "@/src/providers/cart-provider";
import { useTranslations } from "next-intl";

export default function OrderSummary() {
  const t = useTranslations("cart");
  const { cart } = useCartContext();
  return (
    <div className="p-4 bg-white rounded-[16px]">
      <div className="flex gap-2 items-center mb-4">
        <h3 className="font-bold">{t("orderSummary")}</h3>
        <p className="py-1 px-2 rounded-full bg-gray-2 text-gray text-xs">
          {cart?.cart.summary.item_count} {t("items")}
        </p>
      </div>
      {/* subtotal */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("subtotal")}</p>
        <Price
          currentPrice={(cart?.cart.summary?.subtotal ?? 0) / 100}
          size="xs"
          currency={cart?.cart?.currency}
        />
      </div>
      {/* shipping fee */}
      <div className="flex justify-between mb-3">
        <p className=" text-gray">{t("shippingFee")}</p>
        <Price
          currentPrice={(cart?.cart.summary.estimated_shipping ?? 0) / 100}
          size="xs"
          currency={cart?.cart?.currency}
          className="font-normal text-gray"
        />
      </div>
      {/* discount */}
      {!!cart?.cart?.summary?.discount && (
        <div className="flex justify-between mb-3 text-green">
          <p className=" text-green">{t("discount")}</p>
          <Price
            currentPrice={(cart?.cart?.summary?.discount ?? 0) / 100}
            currency={cart?.cart?.currency}
            size="xs"
            className="text-green"
          />
        </div>
      )}
      {/* tax */}
      <div className="flex justify-between pb-4 border-b border-border border-dashed mb-4">
        <p className=" text-gray">{t("tax")}</p>
        <Price
          currentPrice={(cart?.cart.summary.estimated_tax ?? 0) / 100}
          size="xs"
          currency={cart?.cart?.currency}
          className="font-normal text-gray"
        />
      </div>
      {/* total */}
      <div className="flex justify-between">
        <p className=" text-lg font-bold">{t("total")}</p>
        <Price
          currentPrice={(cart?.cart.summary.estimated_total ?? 0) / 100}
          currency={cart?.cart?.currency}
        />
      </div>
    </div>
  );
}
