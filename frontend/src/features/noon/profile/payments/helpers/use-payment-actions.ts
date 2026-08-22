"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import {
  deletePaymentMethod as deletePaymentMethodRequest,
  setDefaultPaymentMethod as setDefaultPaymentMethodRequest,
} from "../api/payments.actions";

/** Feature-level payment method actions (delete, set default) — not the add-card form. */
export function usePaymentActions() {
  const t = useTranslations("profile");
  const router = useRouter();

  const deletePaymentMethod = async (id: string) => {
    try {
      await deletePaymentMethodRequest(id);
      toast.success(t("paymentMethodDeleted"));
      router.refresh();
    } catch (error) {
      toast.error(t("paymentMethodDeleteFailed"));
      throw error;
    }
  };

  const setDefaultPaymentMethod = async (id: string) => {
    try {
      await setDefaultPaymentMethodRequest(id);
      toast.success(t("paymentMethodSetDefault"));
      router.refresh();
    } catch {
      toast.error(t("paymentMethodSetDefaultFailed"));
    }
  };

  return { deletePaymentMethod, setDefaultPaymentMethod };
}
