import { getTranslations } from "next-intl/server";
import { KeyRoundIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import SettingsRow from "./settings-row";

export default async function ChangePasswordCard() {
  const t = await getTranslations("profile");

  return (
    <Link
      href="/security-settings/change-password"
      className="block hover:bg-gray-2/40"
    >
      <SettingsRow
        icon={<KeyRoundIcon className="size-5" />}
        title={t("changePasswordTitle")}
        subtitle={t("changePasswordRowHint")}
      />
    </Link>
  );
}
