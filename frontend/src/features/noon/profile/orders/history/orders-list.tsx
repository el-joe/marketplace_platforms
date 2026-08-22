import { getTranslations } from "next-intl/server";
import { format } from "date-fns";
import OrderGroupCard from "@/src/components/shared/order-group-card";
import {
  getOrderStatusLabelKey,
  isInProgressStatus,
  isNegativeStatus,
} from "../helpers/to-order-status";
import type { OrderListItem } from "../helpers/types";
import Card from "@/src/components/shared/Card";

type Props = {
  orders: OrderListItem[];
};

export default async function OrdersList({ orders }: Props) {
  const t = await getTranslations("profile");

  const inProgress = orders.filter((order) => isInProgressStatus(order.status));

  const completed = orders.filter((order) => !isInProgressStatus(order.status));

  const toCardProps = (order: OrderListItem) => {
    const estimatedDeliveryDate = order.sub_orders[0]?.estimated_delivery_date;
    const items = order.sub_orders.flatMap((subOrder) => subOrder.items);

    return {
      title: t(getOrderStatusLabelKey(order.status)),
      isNegative: isNegativeStatus(order.status),
      badgeLabel:
        isInProgressStatus(order.status) && estimatedDeliveryDate
          ? t("estimatedDeliveryBy", {
              date: format(new Date(estimatedDeliveryDate), "d MMM"),
            })
          : undefined,
      items: items.map((item) => ({
        id: item.id,
        label: item.sku,
        amount: item.line_total,
        href: `/orders/${order.order_number}`,
      })),
      footer: t("orderIdLabel", { id: order.order_number }),
    };
  };

  return (
    <div className="mt-6 flex flex-col gap-8">
      {inProgress.length > 0 && (
        <Card className="p-6">
          <h2 className="mb-3 text-[19px] font-bold text-light">
            {t("inProgress")}
          </h2>
          <div className="flex flex-col gap-4">
            {inProgress.map((order) => (
              <OrderGroupCard
                key={order.order_number}
                {...toCardProps(order)}
              />
            ))}
          </div>
        </Card>
      )}

      {completed.length > 0 && (
        <section>
          <h2 className="mb-3 text-sm font-bold text-gray uppercase">
            {t("completed")}
          </h2>
          <div className="flex flex-col gap-4">
            {completed.map((order) => (
              <OrderGroupCard
                key={order.order_number}
                {...toCardProps(order)}
              />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
