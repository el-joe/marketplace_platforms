"use client";

import { useState, type JSXElementConstructor, type ReactElement } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import type { Wallet } from "../../helpers/types";
import InsufficientBalance from "./insufficient-balance";
import WithdrawalForm from "./withdrawal-form";

type Props = {
  trigger: ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  wallet: Wallet;
};

export const MIN_WITHDRAWAL_AMOUNT = 50;

export default function WithdrawToBank({ trigger, wallet }: Props) {
  const t = useTranslations("profile");
  const [open, setOpen] = useState(false);

  const canWithdraw =
    !wallet.is_frozen && wallet.balance >= MIN_WITHDRAWAL_AMOUNT;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={trigger} />

      <DialogContent className="max-w-2xl p-0 rounded-none">
        <DialogHeader className="border-b border-border px-4 py-3">
          <DialogTitle className="text-[19px] font-semibold text-light">
            {t("withdrawModalTitle")}
          </DialogTitle>
        </DialogHeader>

        {canWithdraw ? (
          <WithdrawalForm onSuccess={() => setOpen(false)} />
        ) : (
          <InsufficientBalance
            onClose={() => setOpen(false)}
            minWithdrawalAmount={`${wallet.currency} ${MIN_WITHDRAWAL_AMOUNT.toFixed(2)}`}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}
