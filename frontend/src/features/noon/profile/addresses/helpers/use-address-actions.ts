"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import {
  deleteAddress as deleteAddressRequest,
  setDefaultAddress as setDefaultAddressRequest,
} from "../api/address.actions";

/**
 * Feature-level address actions that aren't the create/update form (delete,
 * set default). Keep separate from use-address-form-actions.ts, matching
 * the same form-actions/feature-actions split used in profile/.
 */
export function useAddressActions() {
  const t = useTranslations("profile");
  const router = useRouter();

  const deleteAddress = async (id: string) => {
    try {
      await deleteAddressRequest(id);
      toast.success(t("addressDeleted"));
      router.refresh();
    } catch (error) {
      toast.error(t("addressDeleteFailed"));
      throw error;
    }
  };

  const setDefaultAddress = async (id: string) => {
    try {
      await setDefaultAddressRequest(id);
      toast.success(t("addressSetDefault"));
      router.refresh();
    } catch {
      toast.error(t("addressSetDefaultFailed"));
    }
  };

  return { deleteAddress, setDefaultAddress };
}
