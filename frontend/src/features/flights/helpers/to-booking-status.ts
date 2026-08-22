import type { VariantProps } from "class-variance-authority";
import type { badgeVariants } from "@/src/components/ui/badge";
import type { BookingStatus } from "./types";

type BadgeVariant = VariantProps<typeof badgeVariants>["variant"];

const STATUS_VARIANTS: Record<BookingStatus, BadgeVariant> = {
  pending_documents: "yellow",
  confirmed: "green",
  cancelled: "red",
  completed: "gray",
};

export function bookingStatusVariant(status: BookingStatus): BadgeVariant {
  return STATUS_VARIANTS[status] ?? "gray";
}

export function isCancellableStatus(status: BookingStatus): boolean {
  return status === "pending_documents" || status === "confirmed";
}
