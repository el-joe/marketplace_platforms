import { getLocale, getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import OrderItemRow from "../../history/order-item-row";
import { isInProgressStatus } from "../../helpers/to-order-status";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function ItemSummaryCard({ order }: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const statusLabel =
    locale === "ar" ? order.status_label_ar : order.status_label_en;
  const items = order.sub_orders.flatMap((subOrder) => subOrder.items);

  return (
    <div>
      <h2 className="font-bold text-lg">{t("itemSummary")}</h2>

      <Card className="mt-3 border border-border p-6">
        {isInProgressStatus(order.status) && (
          <div className="flex items-center gap-3">
            <Badge variant="green">{statusLabel}</Badge>
          </div>
        )}

        <div className="mt-1 divide-y divide-border">
          {items.map((item) => (
            <OrderItemRow key={item.id} item={item} />
          ))}
        </div>

        <p className="text-right text-xs text-gray">
          {t("orderIdLabel", { id: order.order_number })}
        </p>
      </Card>
    </div>
  );
}
