"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import {
  revokeAllDevices as revokeAllDevicesRequest,
  revokeDevice as revokeDeviceRequest,
} from "../api/security.actions";

/** Feature-level device session actions (revoke one, revoke all others) — not the delete-account flow. */
export function useDeviceActions() {
  const t = useTranslations("profile");
  const router = useRouter();

  const revokeDevice = async (id: string) => {
    try {
      await revokeDeviceRequest(id);
      toast.success(t("deviceSignedOut"));
      router.refresh();
    } catch (error) {
      toast.error(t("deviceSignOutFailed"));
      throw error;
    }
  };

  const revokeAllDevices = async () => {
    try {
      await revokeAllDevicesRequest();
      toast.success(t("allDevicesSignedOut"));
      router.refresh();
    } catch (error) {
      toast.error(t("allDevicesSignOutFailed"));
      throw error;
    }
  };

  return { revokeDevice, revokeAllDevices };
}
