import { notFound } from "next/navigation";
import OrderSummary from "@/src/features/noon/profile/orders/summary";
import { getOrderByNumber } from "@/src/features/noon/profile/orders/api/orders.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function OrderSummaryPage({ params }: Props) {
  const { id } = await params;
  const order = await getOrderByNumber(id);

  if (!order) notFound();

  return <OrderSummary order={order} />;
}
