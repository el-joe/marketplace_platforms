import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { getActiveSessions } from "../api/security.actions";
import ThisDeviceCard from "./components/this-device-card";
import OtherDevicesList from "./components/other-devices-list";
import SignOutAllTrigger from "./components/sign-out-all-trigger";

export default async function ManageDevices() {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const { devices } = await getActiveSessions();

  const [thisDevice, ...otherDevices] = [...devices].sort(
    (a, b) =>
      new Date(b.last_used_at).getTime() - new Date(a.last_used_at).getTime(),
  );

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

      <h1 className="mt-2 text-[28px] font-bold">{t("manageDevicesTitle")}</h1>
      <p className="mt-1 text-sm text-gray">{t("manageDevicesHint")}</p>

      {thisDevice && (
        <div className="mt-6">
          <h2 className="mb-3 text-sm font-semibold text-gray">
            {t("thisDevice")}
          </h2>
          <ThisDeviceCard device={thisDevice} />
        </div>
      )}

      {otherDevices.length > 0 && (
        <>
          <div className="mt-6">
            <h2 className="mb-3 text-sm font-semibold text-gray">
              {t("otherDevicesCount", { count: otherDevices.length })}
            </h2>
            <OtherDevicesList devices={otherDevices} />
          </div>

          <div className="mt-6">
            <SignOutAllTrigger />
          </div>
        </>
      )}
    </>
  );
}
