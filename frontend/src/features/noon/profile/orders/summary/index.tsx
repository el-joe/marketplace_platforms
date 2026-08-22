import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import OrderIdDateCard from "../history/order-id-date-card";
import OrderDetailsCard from "./components/order-details-card";
import DeliveryAddressSummaryCard from "./components/delivery-address-summary-card";
import PaymentDetailsCard from "./components/payment-details-card";
import InvoiceCard from "./components/invoice-card";
import ItemSummaryCard from "./components/item-summary-card";
import type { OrderDetail } from "../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function OrderSummary({ order }: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const estimatedDeliveryDate =
    order.sub_orders[0]?.tracking.estimated_delivery_date ?? null;

  return (
    <div>
      <Link
        href={`/orders/${order.order_number}`}
        className="flex items-center gap-2 text-[28px] font-bold"
      >
        {locale === "ar" ? (
          <ArrowRightIcon className="size-6" />
        ) : (
          <ArrowLeftIcon className="size-6" />
        )}
        {t("orderSummaryTitle")}
      </Link>

      <div className="mt-4">
        <OrderIdDateCard
          orderId={order.order_number}
          orderDate={order.placed_at}
          idLabel={t("orderShipmentId")}
        />
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <OrderDetailsCard order={order} />
        <DeliveryAddressSummaryCard
          address={order.shipping_address}
          estimatedDeliveryDate={estimatedDeliveryDate}
        />
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <PaymentDetailsCard paymentMethod={order.payment_method} />
        <InvoiceCard orderNumber={order.order_number} status={order.status} />
      </div>

      <div className="mt-6">
        <ItemSummaryCard order={order} />
      </div>
    </div>
  );
}
