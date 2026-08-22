"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import {
  createAddress as createAddressRequest,
  updateAddress as updateAddressRequest,
} from "@/src/services/address";
import { toAddressPayload, type AddressWizardData } from "./to-address-payload";

export function useAddressFormActions() {
  const t = useTranslations("profile");
  const router = useRouter();

  const saveNewAddress = async (data: AddressWizardData) => {


    try {
      await createAddressRequest(toAddressPayload(data));
      toast.success(t("addressSaved"));
      router.refresh();
    } catch (error) {
      toast.error(t("addressSaveFailed"));
      throw error;
    }
  };

  const saveExistingAddress = async (id: string, data: AddressWizardData) => {
    try {
      await updateAddressRequest(id, toAddressPayload(data));
      toast.success(t("addressSaved"));
      router.refresh();
    } catch (error) {
      toast.error(t("addressSaveFailed"));
      throw error;
    }
  };

  return { saveNewAddress, saveExistingAddress };
}
