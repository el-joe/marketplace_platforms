import { getTranslations } from "next-intl/server";
import OrderIdDateCard from "../history/order-id-date-card";
import TrackingStatusCard from "./components/tracking-status-card";
import DeliveryAddressCard from "./components/delivery-address-card";
import OrderSummaryLink from "./components/order-summary-link";
import ItemSummaryCard from "./components/item-summary-card";
import type { OrderDetail } from "../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function OrderTracking({ order }: Props) {
  const t = await getTranslations("profile");

  return (
    <div>
      <h1 className="text-[28px] font-bold">{t("trackingDetails")}</h1>

      <div className="mt-4">
        <OrderIdDateCard
          orderId={order.order_number}
          orderDate={order.placed_at}
        />
      </div>

      <div className="mt-4">
        <TrackingStatusCard order={order} />
      </div>

      <div className="mt-4">
        <DeliveryAddressCard address={order.shipping_address} />
      </div>

      <div className="mt-4">
        <OrderSummaryLink orderNumber={order.order_number} />
      </div>

      <div className="mt-4">
        <ItemSummaryCard order={order} />
      </div>
    </div>
  );
}
