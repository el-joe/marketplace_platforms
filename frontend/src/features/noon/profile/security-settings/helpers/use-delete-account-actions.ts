"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { useAuthContext } from "@/src/providers/auth-provider";
import { deleteProfile } from "../api/security.actions";

/** DELETE /profile takes no body, so the reason picked in the dialog is UI-only — not sent to the API. */
export function useDeleteAccountActions() {
  const t = useTranslations("profile");
  const router = useRouter();
  const { logout } = useAuthContext();

  const deleteAccount = async () => {
    try {
      await deleteProfile();
      logout();
      toast.success(t("accountDeleted"));
      router.push("/");
    } catch (error) {
      toast.error(t("accountDeleteFailed"));
      throw error;
    }
  };

  return { deleteAccount };
}
