import { notFound } from "next/navigation";
import CancelItems from "@/src/features/noon/profile/orders/cancel-items";
import { getOrderByNumber } from "@/src/features/noon/profile/orders/api/orders.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function CancelItemsPage({ params }: Props) {
  const { id } = await params;
  const order = await getOrderByNumber(id);

  if (!order) notFound();

  return <CancelItems order={order} />;
}
