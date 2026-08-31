import { trackingSteps, inProgressStatuses } from "./constants";
import type { OrderDetail, OrderStatus, TrackingEvent } from "./types";

type TrackingStage = (typeof trackingSteps)[number]["stage"];

export function getStageIndex(stage: TrackingStage): number {
  return trackingSteps.findIndex((step) => step.stage === stage);
}

export function isInProgressStatus(status: OrderStatus): boolean {
  return (inProgressStatuses as readonly string[]).includes(status);
}

const statusLabelKeys: Record<OrderStatus, string> = {
  placed: "orderStatusPlaced",
  confirmed: "orderStatusConfirmed",
  partially_shipped: "orderStatusPartiallyShipped",
  shipped: "orderStatusShipped",
  partially_delivered: "orderStatusPartiallyDelivered",
  delivered: "orderStatusDelivered",
  completed: "orderStatusCompleted",
  cancelled: "orderStatusCancelled",
  refunded: "orderStatusRefunded",
  disputed: "orderStatusDisputed",
};

/**
 * List Orders doesn't return a bilingual status_label like Get Order Detail
 * does — this maps the raw status to a local translation key instead.
 */
export function getOrderStatusLabelKey(status: OrderStatus): string {
  return statusLabelKeys[status];
}

export function isNegativeStatus(status: OrderStatus): boolean {
  return status === "cancelled" || status === "refunded" || status === "disputed";
}

const trackingBannerKeys: Partial<Record<OrderStatus, string>> = {
  placed: "trackingBannerPlaced",
  confirmed: "confirmedOnTimeBanner",
  partially_shipped: "trackingBannerPartiallyShipped",
  shipped: "trackingBannerShipped",
  delivered: "trackingBannerDelivered",
};

/**
 * The tracking status card's banner subtext is status-specific; statuses
 * outside the active placed -> delivered flow (cancelled, refunded, etc.)
 * fall back to the confirmed-stage copy since this card isn't shown for them.
 */
export function getTrackingBannerKey(status: OrderStatus): string {
  return trackingBannerKeys[status] ?? "confirmedOnTimeBanner";
}

/**
 * trackingSteps' stages mirror the order status enum directly (placed ->
 * confirmed -> shipped -> partially_delivered -> delivered), so this maps
 * straight off order.status. "partially_shipped" isn't its own visual step —
 * it folds onto "shipped" since shipping has already begun. "completed"
 * collapses onto "delivered", and terminal negative statuses
 * (cancelled/refunded/disputed) fall back to "placed".
 */
export function getTrackingStage(order: OrderDetail): TrackingStage {
  switch (order.status) {
    case "placed":
    case "confirmed":
    case "shipped":
    case "partially_delivered":
    case "delivered":
      return order.status;
    case "partially_shipped":
      return "shipped";
    case "completed":
      return "delivered";
    default:
      return "placed";
  }
}

export function getLatestTrackingEvent(
  events: TrackingEvent[],
): TrackingEvent | undefined {
  return events[events.length - 1];
}
