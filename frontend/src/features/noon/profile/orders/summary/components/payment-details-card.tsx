import { getLocale, getTranslations } from "next-intl/server";
import { BanknoteIcon, Undo2Icon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { Badge } from "@/src/components/ui/badge";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import BankTransferCard from "@/src/features/noon/checkout/success/bank-transfer-card";
import type {
  OrderDetail,
  OrderRefund,
  PaymentMethod,
} from "../../helpers/types";

type Props = {
  orderNumber: string;
  paymentMethod: PaymentMethod;
  paymentStatus?: string;
  bankTransferDetails: OrderDetail["bank_transfer_details"];
  refunds?: OrderRefund[];
};

const knownPaymentStatuses = [
  "pending",
  "authorized",
  "captured",
  "failed",
  "refunded",
  "partially_refunded",
];

const refundMethodLabelKeys: Record<string, string> = {
  wallet: "refundToWallet",
  card: "refundToCard",
};

const paymentMethodLabelKeys: Record<PaymentMethod, string> = {
  cod: "cashOnDelivery",
  card: "cardPayment",
  wallet: "paymentMethodWallet",
  bnpl: "paymentMethodBnpl",
  bank_transfer: "paymentMethodBankTransfer",
};

// FIX-5: bank transfer details + proof upload were only ever reachable from
// the one-time post-checkout success screen. Surface them here too — via
// the same bank-transfer-card.tsx/use-bank-transfer-proof.ts used there —
// so customers can always find how to pay from their normal order history.
export default async function PaymentDetailsCard({
  orderNumber,
  paymentMethod,
  paymentStatus,
  bankTransferDetails,
  refunds = [],
}: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();

  return (
    <Card className="border border-border p-6">
      <h2 className="font-bold text-lg">{t("paymentDetailsLabel")}</h2>

      <Badge variant="green" className="mt-4">
        <BanknoteIcon className="size-3.5" />
        {t(paymentMethodLabelKeys[paymentMethod])}
      </Badge>

      {paymentStatus && knownPaymentStatuses.includes(paymentStatus) && (
        <p className="mt-3 text-sm text-gray">
          {t("paymentStatusLabel")}:{" "}
          <span className="font-medium text-foreground">
            {t(`paymentStatus_${paymentStatus}`)}
          </span>
        </p>
      )}

      {/* R2: refunds (wallet / original payment) were invisible on the order page */}
      {refunds.length > 0 && (
        <div className="mt-4 border-t border-border pt-4">
          <h3 className="font-semibold text-sm">{t("refundsLabel")}</h3>
          <ul className="mt-2 flex flex-col gap-3">
            {refunds.map((refund, index) => (
              <li
                key={`${refund.refunded_at ?? "refund"}-${index}`}
                className="flex items-start justify-between gap-3 text-sm"
              >
                <div className="flex items-start gap-2">
                  <Undo2Icon className="size-4 mt-0.5 text-gray" />
                  <div>
                    <p className="font-medium">
                      {t(
                        (refund.method && refundMethodLabelKeys[refund.method]) ||
                          "refundMethodPending",
                      )}
                    </p>
                    {refund.refunded_at && (
                      <p className="text-xs text-gray">
                        {new Date(refund.refunded_at).toLocaleDateString(
                          locale.startsWith("ar") ? "ar" : "en-GB",
                          { day: "numeric", month: "short", year: "numeric" },
                        )}
                      </p>
                    )}
                  </div>
                </div>
                <div className="flex flex-col items-end gap-1">
                  <Price
                    currentPrice={refund.amount}
                    currency={refund.currency as CurrencyCode}
                    size="xs"
                  />
                  <Badge
                    variant={
                      refund.status === "completed"
                        ? "green"
                        : refund.status === "failed" ||
                            refund.status === "rejected"
                          ? "red"
                          : "orange"
                    }
                  >
                    {t(`refundStatus_${refund.status}`)}
                  </Badge>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {paymentMethod === "bank_transfer" && bankTransferDetails && (
        <div className="mt-4">
          <BankTransferCard
            orderNumber={orderNumber}
            details={bankTransferDetails.details}
            alreadyUploaded={Boolean(bankTransferDetails.proof_uploaded_at)}
          />
        </div>
      )}
    </Card>
  );
}
