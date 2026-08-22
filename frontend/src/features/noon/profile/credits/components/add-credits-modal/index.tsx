"use client";

import { JSXElementConstructor, useState } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import SelectMethod from "./select-method";
import RedeemGiftCard from "./redeem-gift-card";

type Step = "select" | "redeem-giftcard";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
};

export default function AddCreditsModal({ trigger }: Props) {
  const t = useTranslations("profile");
  const [step, setStep] = useState<Step>("select");

  return (
    <Dialog onOpenChange={(open) => !open && setStep("select")}>
      <DialogTrigger render={trigger} />
      <DialogContent className="max-w-md gap-0" showCloseButton>
        <DialogHeader className="-mx-4 -mt-4 mb-4 border-b border-border px-6 py-5">
          <DialogTitle className="text-xl font-bold">
            {step === "select"
              ? t("addCreditsModalTitle")
              : t("redeemGiftCardTitle")}
          </DialogTitle>
        </DialogHeader>

        {step === "select" ? (
          <SelectMethod onSelectGiftcards={() => setStep("redeem-giftcard")} />
        ) : (
          <RedeemGiftCard />
        )}
      </DialogContent>
    </Dialog>
  );
}
