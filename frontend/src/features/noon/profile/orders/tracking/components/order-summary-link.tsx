import { getTranslations } from "next-intl/server";
import { ChevronRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";

type Props = {
  orderNumber: string;
};

export default async function OrderSummaryLink({ orderNumber }: Props) {
  const t = await getTranslations("profile");

  return (
    <Link href={`/orders/${orderNumber}/summary`}>
      <Card className="flex items-center justify-between border border-border p-6 hover:bg-muted">
        <div>
          <h2 className="font-bold text-lg">{t("viewOrderInvoiceSummary")}</h2>
          <p className="text-sm text-gray">{t("findInvoiceShippingDetails")}</p>
        </div>
        <ChevronRightIcon className="size-5 text-gray" />
      </Card>
    </Link>
  );
}
