import { getLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { Button } from "@/src/components/ui/button";
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
      <div className="flex items-center justify-between">
        <h2 className="font-bold text-lg">{t("itemSummary")}</h2>

        {isInProgressStatus(order.status) && (
          <Button
            render={<Link href={`/orders/${order.order_number}/cancel`} />}
            nativeButton={false}
            variant="outline"
            className="font-semibold"
          >
            {t("cancelItems")}
          </Button>
        )}
      </div>

      <Card className="mt-3 border border-border p-6">
        <p className="font-bold">{statusLabel}</p>

        <div className="mt-1 divide-y divide-border">
          {items.map((item) => (
            <OrderItemRow key={item.id} item={item} showItemId />
          ))}
        </div>
      </Card>
    </div>
  );
}
