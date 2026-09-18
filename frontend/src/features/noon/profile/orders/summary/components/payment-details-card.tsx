import { getTranslations } from "next-intl/server";
import { BanknoteIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import BankTransferCard from "@/src/features/noon/checkout/success/bank-transfer-card";
import type { OrderDetail, PaymentMethod } from "../../helpers/types";

type Props = {
  orderNumber: string;
  paymentMethod: PaymentMethod;
  bankTransferDetails: OrderDetail["bank_transfer_details"];
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
  bankTransferDetails,
}: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="border border-border p-6">
      <h2 className="font-bold text-lg">{t("paymentDetailsLabel")}</h2>

      <Badge variant="green" className="mt-4">
        <BanknoteIcon className="size-3.5" />
        {t(paymentMethodLabelKeys[paymentMethod])}
      </Badge>

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
