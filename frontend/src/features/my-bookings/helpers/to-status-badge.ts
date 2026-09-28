import type { VariantProps } from "class-variance-authority";
import type { badgeVariants } from "@/src/components/ui/badge";
import type { UnifiedBookingRawStatus } from "./types";

type BadgeVariant = VariantProps<typeof badgeVariants>["variant"];

// Travel-package bookings use "pending_documents" while bookable-unit and
// flight bookings use plain "pending" — normalized here to one display bucket
// so the unified list can show a single consistent badge set.
const DISPLAY_STATUS: Record<UnifiedBookingRawStatus, "pending" | "confirmed" | "cancelled" | "completed"> = {
  pending_documents: "pending",
  pending: "pending",
  confirmed: "confirmed",
  cancelled: "cancelled",
  completed: "completed",
};

const STATUS_VARIANTS: Record<ReturnType<typeof toDisplayStatus>, BadgeVariant> = {
  pending: "yellow",
  confirmed: "green",
  cancelled: "red",
  completed: "gray",
};

export function toDisplayStatus(status: UnifiedBookingRawStatus) {
  return DISPLAY_STATUS[status] ?? "pending";
}

export function bookingStatusVariant(status: UnifiedBookingRawStatus): BadgeVariant {
  return STATUS_VARIANTS[toDisplayStatus(status)];
}
