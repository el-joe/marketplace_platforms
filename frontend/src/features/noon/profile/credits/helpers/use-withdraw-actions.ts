"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import { requestWithdrawal } from "../api/wallet.actions";
import { getWithdrawSchema, type WithdrawFormValues } from "./withdraw.schema";

type Params = {
  onSuccess: () => void;
};

const defaultValues: WithdrawFormValues = {
  amount: "",
  bankName: "",
  bankIban: "",
};

export function useWithdrawActions({ onSuccess }: Params) {
  const t = useTranslations("profile");
  const router = useRouter();

  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useForm<WithdrawFormValues>({
    resolver: zodResolver(getWithdrawSchema(t)),
    defaultValues,
    mode: "onChange",
  });

  const onSubmit = form.handleSubmit(async (data) => {
    setIsSubmitting(true);
    try {
      await requestWithdrawal({
        amount: Number(data.amount),
        bank_name: data.bankName,
        bank_iban: data.bankIban,
      });
      toast.success(t("withdrawalRequested"));
      router.refresh();
      onSuccess();
    } catch (error) {
      const message =
        error instanceof ApiRequestError
          ? error.message
          : t("withdrawalRequestFailed");
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  });

  return { form, isSubmitting, onSubmit };
}
