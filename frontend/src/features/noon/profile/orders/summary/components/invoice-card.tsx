import { getTranslations } from "next-intl/server";
import { FileTextIcon, DownloadIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { isInProgressStatus } from "../../helpers/to-order-status";
import type { OrderStatus } from "../../helpers/types";

type Props = {
  orderNumber: string;
  status: OrderStatus;
};

export default async function InvoiceCard({ orderNumber, status }: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="border border-border p-6">
      <div className="flex items-start justify-between">
        <h2 className="flex items-center gap-2 font-bold text-lg">
          <FileTextIcon className="size-5" />
          {t("viewInvoice")}
        </h2>
        <DownloadIcon className="size-5 text-gray" />
      </div>

      {isInProgressStatus(status) ? (
        <p className="mt-2 text-sm text-gray">
          {t("invoiceAvailableOnceItemsPacked")}{" "}
          <Link href={`/orders/${orderNumber}`} className="text-blue-3 underline">
            {t("seeOrderStatus")}
          </Link>
        </p>
      ) : null}
    </Card>
  );
}
