import { CheckIcon, Truck, ClipboardCheck, PackageIcon } from "lucide-react";

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
  { stage: "processing", labelKey: "stepProcessing", icon: Truck },
  { stage: "confirmed", labelKey: "stepConfirmed", icon: CheckIcon },
  { stage: "dispatched", labelKey: "stepDispatched", icon: ClipboardCheck },
  { stage: "delivered", labelKey: "deliveryByTomorrow", icon: PackageIcon },
] as const;

/** Order statuses shown under the list page's "In progress" group. */
export const inProgressStatuses = [
  "placed",
  "confirmed",
  "partially_shipped",
  "shipped",
  "partially_delivered",
] as const;
