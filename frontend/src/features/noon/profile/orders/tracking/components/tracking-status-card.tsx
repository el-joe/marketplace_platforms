import { getLocale, getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import { cn } from "@/src/lib/utils";
import TrackingStepper from "./tracking-stepper";
import { getTrackingBannerKey } from "../../helpers/to-order-status";
import type { OrderDetail } from "../../helpers/types";
import { getLanguage } from "@/src/helpers/handleRegionAndLocal";

type Props = {
  order: OrderDetail;
  isNegative: boolean;
};

export default async function TrackingStatusCard({ order, isNegative }: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();

  const lang = getLanguage(locale);

  const statusLabel =
    lang === "ar" ? order.status_label_ar : order.status_label_en;
  const bannerKey = getTrackingBannerKey(order.status);

  return (
    <Card className="border border-border p-6">
      <div
        className={cn(
          "rounded-xl p-4",
          isNegative ? "bg-light-red" : "bg-light-green",
        )}
      >
        <p
          className={cn(
            "text-lg font-bold",
            isNegative ? "text-red" : "text-primary",
          )}
        >
          {statusLabel}
        </p>
        <p
          className={cn(
            "mt-1 text-sm",
            isNegative ? "text-red" : "text-primary",
          )}
        >
          {t(bannerKey)}
        </p>
      </div>

      {!isNegative && (
        <div className="mt-6">
          <TrackingStepper order={order} />
        </div>
      )}
    </Card>
  );
}
