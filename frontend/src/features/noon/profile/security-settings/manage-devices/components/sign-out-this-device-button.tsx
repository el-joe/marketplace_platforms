"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { useAuthContext } from "@/src/providers/auth-provider";

/** Signing out "this device" is just an ordinary logout, not a session-revoke call. */
export default function SignOutThisDeviceButton() {
  const t = useTranslations("profile");
  const { logout } = useAuthContext();

  return (
    <Button
      variant="outline"
      className="h-10 shrink-0 rounded-full px-5 font-semibold"
      onClick={logout}
    >
      {t("signOut")}
    </Button>
  );
}
