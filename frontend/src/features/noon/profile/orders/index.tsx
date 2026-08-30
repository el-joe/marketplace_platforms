import { getTranslations } from "next-intl/server";
import OrdersEmptyState from "./history/orders-empty-state";
import OrdersList from "./history/orders-list";
import OrdersFilter from "./history/orders-filter";
import { getOrders } from "./api/orders.actions";
import type { OrderStatus } from "./helpers/types";

type Props = {
  status?: string;
};

export default async function Orders({ status }: Props) {
  const t = await getTranslations("profile");

  const { items: orders } = await getOrders({
    status: status as OrderStatus | undefined,
  });

  return (
    <>
      <div className="flex items-center justify-between">
        <h1 className="text-[28px] font-bold  text-light">
          {t("ordersTitle")}
        </h1>
        <OrdersFilter />
      </div>

      {orders.length === 0 ? (
        <OrdersEmptyState />
      ) : (
        <OrdersList orders={orders} />
      )}
    </>
  );
}
