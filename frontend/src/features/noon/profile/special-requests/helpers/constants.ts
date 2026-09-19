import type { SpecialRequestStatus } from "../api/special-requests.actions";

export const ALL_CITIES = "all";

export const STATUS_VARIANT: Record<
  SpecialRequestStatus,
  "blue" | "yellow" | "gray"
> = {
  open: "blue",
  in_progress: "yellow",
  closed: "gray",
};
