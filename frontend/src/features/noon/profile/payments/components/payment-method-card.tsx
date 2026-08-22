import { format } from "date-fns";
import { useTranslations } from "next-intl";
import {
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  AlertCircleIcon,
} from "lucide-react";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { Link } from "@/i18n/navigation";
import type { PaymentTransaction } from "../helpers/types";

type Props = {
  transaction: PaymentTransaction;
};

const STATUS_ICON = {
  succeeded: <CheckCircleIcon className="size-4 text-green" />,
  failed: <XCircleIcon className="size-4 text-red" />,
  pending: <ClockIcon className="size-4 text-yellow-500" />,
  cancelled: <AlertCircleIcon className="size-4 text-gray" />,
};

const STATUS_COLOR: Record<string, string> = {
  succeeded: "text-green",
  failed: "text-red",
  pending: "text-yellow-500",
  cancelled: "text-gray",
};

const TYPE_LABEL: Record<string, string> = {
  authorization: "Authorization",
  capture: "Capture",
  sale: "Payment",
  refund: "Refund",
  void: "Void",
  chargeback: "Chargeback",
};

export default function PaymentTransactionCard({ transaction }: Props) {
  const t = useTranslations("profile");

  const statusColor = STATUS_COLOR[transaction.status] ?? "text-gray";
  const typeLabel = TYPE_LABEL[transaction.type] ?? transaction.type;

  return (
    <Card className="p-5 flex flex-col gap-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-semibold text-sm">{typeLabel}</p>
          <p className="text-xs text-gray mt-0.5 uppercase tracking-wide">
            {transaction.gateway}
          </p>
        </div>
        <span
          className={`flex items-center gap-1 text-xs font-medium ${statusColor}`}
        >
          {STATUS_ICON[transaction.status]}
          {t(`paymentStatus.${transaction.status}` as any, {
            defaultValue: transaction.status,
          })}
        </span>
      </div>

      <div className="flex items-center justify-between">
        <Price
          currentPrice={transaction.amount}
          currency={transaction.currency as CurrencyCode}
          size="sm"
        />
        <span className="text-xs text-gray">
          {transaction.processed_at
            ? format(new Date(transaction.processed_at), "dd MMM yyyy, hh:mm a")
            : format(new Date(transaction.created_at), "dd MMM yyyy")}
        </span>
      </div>

      {transaction.order_number && (
        <Link
          href={`/orders/${transaction.order_number}`}
          className="text-xs text-blue-2 hover:underline"
        >
          {t("orderLabel")} #{transaction.order_number}
        </Link>
      )}
    </Card>
  );
}
