import type { badgeVariants } from "@/src/components/ui/badge";
import type { VariantProps } from "class-variance-authority";

type BadgeVariant = VariantProps<typeof badgeVariants>["variant"];

const STATUS_VARIANTS: Record<string, BadgeVariant> = {
  open: "blue",
  in_progress: "yellow",
  resolved: "green",
  closed: "gray",
  under_review: "yellow",
  escalated: "orange",
  rejected: "red",
};

export function statusVariant(status: string): BadgeVariant {
  return STATUS_VARIANTS[status] ?? "gray";
}

const PRIORITY_VARIANTS: Record<string, BadgeVariant> = {
  low: "gray",
  normal: "blue",
  high: "orange",
  urgent: "red",
};

export function priorityVariant(priority: string): BadgeVariant {
  return PRIORITY_VARIANTS[priority] ?? "gray";
}
