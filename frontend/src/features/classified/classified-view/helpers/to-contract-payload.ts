import type { CustomerContractPayload } from "@/src/services/customer-contracts";

/** Reads the contract out of an error response's `data` (`{ contract }`), or null when it is not there. */
export function toContractPayload(data: unknown): CustomerContractPayload | null {
  if (typeof data !== "object" || data === null || !("contract" in data)) {
    return null;
  }
  const contract = (data as { contract: unknown }).contract;
  return typeof contract === "object" && contract !== null
    ? (contract as CustomerContractPayload)
    : null;
}
