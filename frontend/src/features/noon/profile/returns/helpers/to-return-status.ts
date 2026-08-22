const NEGATIVE_STATUSES = ["rejected", "cancelled"];

const STATUS_LABEL_KEYS: Record<string, string> = {
  requested: "returnStatusRequested",
  approved: "returnStatusApproved",
  processing: "returnStatusProcessing",
  completed: "returnStatusCompleted",
  rejected: "returnStatusRejected",
  cancelled: "returnStatusCancelled",
};

export function isNegativeReturnStatus(status: string): boolean {
  return NEGATIVE_STATUSES.includes(status);
}

/** Returns null for a status outside the known set — the API doesn't expose a fixed enum for it. */
export function getReturnStatusLabelKey(status: string): string | null {
  return STATUS_LABEL_KEYS[status] ?? null;
}
