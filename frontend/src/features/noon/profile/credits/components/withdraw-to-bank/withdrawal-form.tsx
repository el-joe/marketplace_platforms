"use client";

import { FormProvider } from "react-hook-form";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import { useWithdrawActions } from "../../helpers/use-withdraw-actions";

type Props = {
  onSuccess: () => void;
};

export default function WithdrawalForm({ onSuccess }: Props) {
  const t = useTranslations("profile");
  const { form, isSubmitting, onSubmit } = useWithdrawActions({ onSuccess });
  const {
    formState: { isValid },
  } = form;

  return (
    <FormProvider {...form}>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <FormInput
          name="amount"
          type="number"
          label={t("withdrawalAmountLabel")}
          placeholder={t("withdrawalAmountPlaceholder")}
          className="h-12"
        />

        <FormInput
          name="bankName"
          label={t("bankNameLabel")}
          placeholder={t("bankNamePlaceholder")}
          className="h-12"
        />

        <FormInput
          name="bankIban"
          label={t("bankIbanLabel")}
          placeholder={t("bankIbanPlaceholder")}
          className="h-12"
        />

        <Button
          type="submit"
          disabled={!isValid || isSubmitting}
          className="bg-black text-white uppercase font-semibold h-12 rounded-md w-full disabled:bg-gray-2 disabled:text-gray"
        >
          {isSubmitting ? t("saving") : t("withdrawToBank")}
        </Button>
      </form>
    </FormProvider>
  );
}
