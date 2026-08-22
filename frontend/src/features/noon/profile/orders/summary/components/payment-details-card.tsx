import { getTranslations } from "next-intl/server";
import { BanknoteIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import type { PaymentMethod } from "../../helpers/types";

type Props = {
  paymentMethod: PaymentMethod;
};

const paymentMethodLabelKeys: Record<PaymentMethod, string> = {
  cod: "cashOnDelivery",
  card: "cardPayment",
  wallet: "paymentMethodWallet",
  bnpl: "paymentMethodBnpl",
  bank_transfer: "paymentMethodBankTransfer",
};

export default async function PaymentDetailsCard({ paymentMethod }: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="border border-border p-6">
      <h2 className="font-bold text-lg">{t("paymentDetailsLabel")}</h2>

      <Badge variant="green" className="mt-4">
        <BanknoteIcon className="size-3.5" />
        {t(paymentMethodLabelKeys[paymentMethod])}
      </Badge>
    </Card>
  );
}
