"use client";

import { useTranslations } from "next-intl";
import Card from "@/src/components/shared/Card";
import { Switch } from "@/src/components/ui/base-inputs/switch";
import ConfirmDialog from "@/src/components/shared/dialogs/confirm-dialog/confirm-dialog";
import { usePaymentActions } from "../helpers/use-payment-actions";
import type { PaymentMethod } from "../helpers/types";

type Props = {
  paymentMethod: PaymentMethod;
};

export default function PaymentMethodCard({ paymentMethod }: Props) {
  const t = useTranslations("profile");
  const { deletePaymentMethod, setDefaultPaymentMethod } = usePaymentActions();

  return (
    <Card className="overflow-hidden">
      <div className="bg-[radial-gradient(circle_at_70%_35%,#2a45c2,#0a1340_70%)] p-6 text-white">
        <div className="flex items-center justify-between">
          <span className="font-bold">
            {paymentMethod.billing_address?.recipient_name ?? paymentMethod.card_display}
          </span>
          <span className="text-xl font-bold italic uppercase">
            {paymentMethod.card_brand ?? paymentMethod.type}
          </span>
        </div>

        <div className="mt-10 flex items-end justify-between">
          <div>
            <p className="text-xs text-white/60">{t("cardNumber")}</p>
            <p className="mt-1 font-mono tracking-wide">
              <span className="text-white/60">XXXX-XXXX-XXXX-</span>
              <span className="font-bold">{paymentMethod.card_last4 ?? "----"}</span>
            </p>
          </div>
          {paymentMethod.card_exp_month && paymentMethod.card_exp_year && (
            <div className="text-right">
              <p className="text-xs text-white/60">{t("expDate")}</p>
              <p className="mt-1 font-bold">
                {String(paymentMethod.card_exp_month).padStart(2, "0")}/
                {String(paymentMethod.card_exp_year).slice(-2)}
              </p>
            </div>
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-4 px-6 py-4">
        <div className="flex items-center gap-2">
          <span className="font-medium">{t("setDefault")}</span>
          <Switch
            checked={paymentMethod.is_default}
            disabled={paymentMethod.is_default}
            onCheckedChange={() => setDefaultPaymentMethod(paymentMethod.id)}
          />
        </div>

        <ConfirmDialog
          variant="danger"
          triggerText={t("delete")}
          triggerClassName="h-auto px-3 py-1.5 text-xs"
          title={t("deletePaymentMethodConfirmTitle")}
          description={t("deletePaymentMethodConfirmDescription")}
          confirmText={t("deletePaymentMethodConfirmButton")}
          cancelText={t("deleteAddressCancelButton")}
          onConfirm={() => deletePaymentMethod(paymentMethod.id)}
        />
      </div>
    </Card>
  );
}
