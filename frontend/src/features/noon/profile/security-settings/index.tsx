import { getTranslations } from "next-intl/server";
import ManageDevicesCard from "./components/manage-devices-card";
import AccountDeletionCard from "./components/account-deletion-card";
import Card from "@/src/components/shared/Card";

export default async function SecuritySettings() {
  const t = await getTranslations("profile");

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">
        {t("securitySettingsTitle")}
      </h1>

      <Card className="mt-4 divide-y divide-border overflow-hidden">
        <ManageDevicesCard />
        <AccountDeletionCard />
      </Card>
    </>
  );
}
