import { format } from "date-fns";
import { Laptop2Icon, SmartphoneIcon } from "lucide-react";
import { getTranslations } from "next-intl/server";
import type { ReactNode } from "react";
import { getDevicePlatformLabelKey } from "../../helpers/to-device-display";
import type { ActiveSession } from "../../helpers/types";

type Props = {
  device: ActiveSession;
  action?: ReactNode;
};

export default async function DeviceRow({ device, action }: Props) {
  const t = await getTranslations("profile");
  const isMobile = device.platform === "ios" || device.platform === "android";

  return (
    <div className="flex items-center justify-between gap-4 p-4">
      <div className="flex items-center gap-3">
        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-2">
          {isMobile ? (
            <SmartphoneIcon className="size-5" />
          ) : (
            <Laptop2Icon className="size-5" />
          )}
        </div>
        <div>
          <p className="font-bold">{t(getDevicePlatformLabelKey(device.platform))}</p>
          <p className="mt-0.5 text-sm text-gray">
            {t("signedInOn")} {format(new Date(device.last_used_at), "dd MMM yyyy")}
          </p>
        </div>
      </div>

      {action}
    </div>
  );
}
