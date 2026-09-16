import { CheckIcon, Truck, ClipboardCheck, PackageIcon } from "lucide-react";
import type { OrderStatus } from "./types";

export const returnReasons = [
  { value: "changed_mind", labelKey: "reasonChangedMind" },
  { value: "wrong_item", labelKey: "reasonWrongItem" },
  { value: "defective", labelKey: "reasonDefective" },
  { value: "damaged", labelKey: "reasonDamaged" },
  { value: "not_as_described", labelKey: "reasonNotAsDescribed" },
  { value: "size_issue", labelKey: "reasonSizeIssue" },
  { value: "quality_issue", labelKey: "reasonQualityIssue" },
  { value: "arrived_late", labelKey: "reasonArrivedLate" },
  { value: "other", labelKey: "reasonOther" },
] as const;

export const trackingSteps = [
  { stage: "placed", labelKey: "stepPlaced", icon: CheckIcon },
  { stage: "confirmed", labelKey: "stepConfirmed", icon: ClipboardCheck },
  { stage: "shipped", labelKey: "stepShipped", icon: Truck },
  { stage: "partially_delivered", labelKey: "stepPartiallyDelivered", icon: PackageIcon },
  { stage: "delivered", labelKey: "stepDelivered", icon: PackageIcon },
] as const;

/** Order statuses shown under the list page's "In progress" group. */
export const inProgressStatuses = [
  "placed",
  "confirmed",
  "partially_shipped",
  "shipped",
  "partially_delivered",
] as const;

/** All order statuses, in display order, for the orders list status filter. */
export const orderStatusFilterOptions: readonly OrderStatus[] = [
  "placed",
  "confirmed",
  "partially_shipped",
  "shipped",
  "partially_delivered",
  "delivered",
  "completed",
  "cancelled",
  "refunded",
  "disputed",
];
