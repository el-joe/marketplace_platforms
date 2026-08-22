import { getTranslations } from "next-intl/server";
import { LogOutIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import SettingsRow from "./settings-row";

export default async function ManageDevicesCard() {
  const t = await getTranslations("profile");

  return (
    <Link href="/security-settings/manage-devices" className="block hover:bg-gray-2/40">
      <SettingsRow
        icon={<LogOutIcon className="size-5" />}
        title={t("manageDevicesTitle")}
        subtitle={t("manageDevicesHint")}
      />
    </Link>
  );
}
