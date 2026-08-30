import { getTranslations } from "next-intl/server";
import { format } from "date-fns";
import { cn } from "@/src/lib/utils";
import { trackingSteps } from "../../helpers/constants";
import { getStageIndex, getTrackingStage } from "../../helpers/to-order-status";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function TrackingStepper({ order }: Props) {
  const t = await getTranslations("profile");
  const currentIndex = getStageIndex(getTrackingStage(order));
  const subOrder = order.sub_orders[0];

  const stepDates: Record<string, string | undefined> = {
    placed: format(new Date(order.placed_at), "do MMM"),
    shipped: subOrder?.tracking.shipped_at
      ? format(new Date(subOrder.tracking.shipped_at), "do MMM")
      : undefined,
    delivered: subOrder?.tracking.delivered_at
      ? format(new Date(subOrder.tracking.delivered_at), "do MMM")
      : undefined,
  };

  return (
    <div className="flex items-start">
      {trackingSteps.map((step, index) => {
        const isReached = index <= currentIndex;
        const Icon = step.icon;

        return (
          <div
            key={step.stage}
            className="flex flex-1 items-center last:flex-none"
          >
            <div className="flex flex-col items-center text-center">
              <span
                className={cn(
                  "flex size-9 items-center justify-center rounded-full",
                  isReached
                    ? "bg-light-green text-green"
                    : "bg-gray-2 text-light",
                )}
              >
                <Icon className="size-4.5" />
              </span>
              <p
                className={cn(
                  "mt-2 text-sm",
                  index === currentIndex
                    ? "font-bold text-primary"
                    : "text-gray",
                )}
              >
                {t(step.labelKey)}
              </p>
              {stepDates[step.stage] && (
                <p
                  className={cn(
                    "text-xs",
                    index === currentIndex
                      ? "font-semibold text-green"
                      : "text-gray",
                  )}
                >
                  {stepDates[step.stage]}
                </p>
              )}
            </div>

            {index < trackingSteps.length - 1 && (
              <div
                className={cn(
                  "mx-2 h-1 flex-1 rounded-full",
                  index < currentIndex ? "bg-green" : "bg-gray-2",
                )}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
