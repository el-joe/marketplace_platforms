import { notFound } from "next/navigation";
import OrderInvoiceView from "@/src/features/noon/profile/orders/invoice";
import { getOrderInvoice } from "@/src/features/noon/profile/orders/api/orders.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function OrderInvoicePage({ params }: Props) {
  const { id } = await params;
  const invoice = await getOrderInvoice(id);

  if (!invoice) notFound();

  return <OrderInvoiceView invoice={invoice} />;
}
