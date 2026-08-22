import { getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { centsToAmount } from "@/src/lib/utils";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function OrderDetailsCard({ order }: Props) {
  const t = await getTranslations("profile");

  const itemCount = order.sub_orders
    .flatMap((subOrder) => subOrder.items)
    .reduce((total, item) => total + item.quantity, 0);

  const { subtotal_cents, discount_cents, shipping_cents, cod_fee_cents, tax_cents, total_cents } =
    order.summary;
  const fees = shipping_cents + cod_fee_cents;

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
        <Price currentPrice={centsToAmount(subtotal_cents)} size="xs" />
      </div>

      <div className="mt-2 flex items-center justify-between text-sm">
        <p className="text-gray">{t("feesLabel")}</p>
        <Price currentPrice={centsToAmount(fees)} size="xs" />
      </div>

      {discount_cents > 0 && (
        <div className="mt-2 flex items-center justify-between text-sm">
          <p className="text-gray">{t("discountLabel")}</p>
          <p className="text-green">
            -<Price currentPrice={centsToAmount(discount_cents)} size="xs" />
          </p>
        </div>
      )}

      <div className="mt-2 flex items-center justify-between text-sm">
        <p className="text-gray">{t("taxLabel")}</p>
        <Price currentPrice={centsToAmount(tax_cents)} size="xs" />
      </div>

      <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
        <p className="font-bold">
          {t("orderTotalLabel")}{" "}
          <span className="font-normal text-gray text-xs">({t("incVat")})</span>
        </p>
        <Price currentPrice={centsToAmount(total_cents)} size="sm" />
      </div>
    </Card>
  );
}
