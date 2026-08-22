import { getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function OrderDetailsCard({ order }: Props) {
  const t = await getTranslations("profile");

  const itemCount = order.sub_orders
    .flatMap((subOrder) => subOrder.items)
    .reduce((total, item) => total + item.quantity, 0);

  const { subtotal, discount, shipping, cod_fee, tax, total } = order.summary;
  const fees = shipping + cod_fee;

  return (
    <Card className="border border-border p-6">
      <h2 className="font-bold text-lg">{t("orderDetailsLabel")}</h2>

      <div className="mt-4 flex items-center justify-between text-sm">
        <p className="text-gray">
          {t("itemsValueLabel")}{" "}
          {t(itemCount > 1 ? "itemCountPlural" : "itemCountSingular", {
            count: itemCount,
          })}
        </p>
        <Price currentPrice={subtotal} size="xs" />
      </div>

      <div className="mt-2 flex items-center justify-between text-sm">
        <p className="text-gray">{t("feesLabel")}</p>
        <Price currentPrice={fees} size="xs" />
      </div>

      {discount > 0 && (
        <div className="mt-2 flex items-center justify-between text-sm">
          <p className="text-gray">{t("discountLabel")}</p>
          <p className="text-green">
            -<Price currentPrice={discount} size="xs" />
          </p>
        </div>
      )}

      <div className="mt-2 flex items-center justify-between text-sm">
        <p className="text-gray">{t("taxLabel")}</p>
        <Price currentPrice={tax} size="xs" />
      </div>

      <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
        <p className="font-bold">
          {t("orderTotalLabel")}{" "}
          <span className="font-normal text-gray text-xs">({t("incVat")})</span>
        </p>
        <Price currentPrice={total} size="sm" />
      </div>
    </Card>
  );
}
