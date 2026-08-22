import { getTranslations } from "next-intl/server";
import { Trash2Icon } from "lucide-react";
import DeleteAccountDialog from "./delete-account-dialog";
import SettingsRow from "./settings-row";

export default async function AccountDeletionCard() {
  const t = await getTranslations("profile");

  return (
    <DeleteAccountDialog
      trigger={
        <button type="button" className="block w-full hover:bg-gray-2/40">
          <SettingsRow
            icon={<Trash2Icon className="size-5 text-red" />}
            title={t("accountDeletion")}
            subtitle={t("deleteAccount")}
          />
        </button>
      }
    />
  );
}
