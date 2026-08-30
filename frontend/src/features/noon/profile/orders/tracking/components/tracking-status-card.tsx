import { getLocale, getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import TrackingStepper from "./tracking-stepper";
import { getTrackingBannerKey } from "../../helpers/to-order-status";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function TrackingStatusCard({ order }: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const statusLabel =
    locale === "ar" ? order.status_label_ar : order.status_label_en;
  const bannerKey = getTrackingBannerKey(order.status);

  return (
    <Card className="border border-border p-6">
      <div className="rounded-xl bg-light-green p-4">
        <p className="text-lg font-bold text-primary">{statusLabel}</p>
        <p className="mt-1 text-sm text-primary">{t(bannerKey)}</p>
      </div>

      <div className="mt-6">
        <TrackingStepper order={order} />
      </div>
    </Card>
  );
}
