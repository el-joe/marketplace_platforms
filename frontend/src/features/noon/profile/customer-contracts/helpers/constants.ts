import type { MyContractStatus } from "@/src/services/customer-contracts";

export const STATUS_VARIANT: Record<MyContractStatus, "green" | "gray" | "red"> = {
  active: "green",
  superseded: "gray",
  cancelled: "red",
};
