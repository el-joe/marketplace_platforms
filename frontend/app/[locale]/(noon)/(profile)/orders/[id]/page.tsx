import { notFound } from "next/navigation";
import OrderTracking from "@/src/features/noon/profile/orders/tracking";
import { getOrderByNumber } from "@/src/features/noon/profile/orders/api/orders.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function OrderTrackingPage({ params }: Props) {
  const { id } = await params;
  const order = await getOrderByNumber(id);

  if (!order) notFound();

  return <OrderTracking order={order} />;
}
