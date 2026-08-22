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

/**
 * The API doesn't expose a single "current stage" — it's derived from the
 * order status plus the first sub-order's tracking timestamps (shipped_at /
 * delivered_at), since that's what the stepper visualizes.
 */
export function getTrackingStage(order: OrderDetail): TrackingStage {
  const subOrder = order.sub_orders[0];

  if (order.status === "delivered" || order.status === "completed") {
    return "delivered";
  }
  if (subOrder?.tracking.delivered_at) return "delivered";
  if (subOrder?.tracking.shipped_at) return "dispatched";
  if (order.status === "confirmed") return "confirmed";
  if (order.status === "placed") return "processing";
  return "confirmed";
}

export function getLatestTrackingEvent(
  events: TrackingEvent[],
): TrackingEvent | undefined {
  return events[events.length - 1];
}
