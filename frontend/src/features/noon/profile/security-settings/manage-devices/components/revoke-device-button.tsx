"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { useDeviceActions } from "../../helpers/use-device-actions";

type Props = {
  deviceId: string;
};

export default function RevokeDeviceButton({ deviceId }: Props) {
  const t = useTranslations("profile");
  const { revokeDevice } = useDeviceActions();
  const [isRevoking, setIsRevoking] = useState(false);

  const handleClick = async () => {
    setIsRevoking(true);
    try {
      await revokeDevice(deviceId);
    } catch {
      setIsRevoking(false);
    }
  };

  return (
    <Button
      variant="outline"
      className="h-10 shrink-0 rounded-full px-5 font-semibold"
      disabled={isRevoking}
      onClick={handleClick}
    >
      {t("signOut")}
    </Button>
  );
}
