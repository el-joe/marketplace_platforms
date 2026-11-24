import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import ChangePasswordForm from "./components/change-password-form";

export default async function ChangePassword() {
  const t = await getTranslations("profile");
  const locale = await getLocale();

  return (
    <>
      <Link
        href="/security-settings"
        className="flex items-center gap-2 text-sm text-gray"
      >
        {locale === "ar" ? (
          <ArrowRightIcon className="size-4" />
        ) : (
          <ArrowLeftIcon className="size-4" />
        )}
        {t("backToSecuritySettings")}
      </Link>

      <h1 className="mt-2 text-[28px] font-bold">{t("changePasswordTitle")}</h1>
      <p className="mt-1 text-sm text-gray">{t("changePasswordPageHint")}</p>

      <Card className="mt-6 p-6">
        <ChangePasswordForm />
      </Card>
    </>
  );
}
