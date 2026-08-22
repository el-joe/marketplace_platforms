"use client";

import { useTranslations } from "next-intl";
import ConfirmDialog from "@/src/components/shared/dialogs/confirm-dialog/confirm-dialog";
import { useDeviceActions } from "../../helpers/use-device-actions";

export default function SignOutAllTrigger() {
  const t = useTranslations("profile");
  const { revokeAllDevices } = useDeviceActions();

  return (
    <ConfirmDialog
      variant="danger"
      triggerText={t("signOutAllOtherDevices")}
      triggerClassName="w-full h-14 rounded-2xl bg-white hover:bg-white text-red font-semibold normal-case shadow-none border border-border"
      title={t("signOutAllConfirmTitle")}
      description={t("signOutAllConfirmDescription")}
      confirmText={t("signOutAllOtherDevices")}
      cancelText={t("deleteAddressCancelButton")}
      onConfirm={revokeAllDevices}
    />
  );
}
