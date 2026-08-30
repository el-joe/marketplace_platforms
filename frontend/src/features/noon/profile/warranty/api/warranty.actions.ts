import { fetchInstance } from "@/src/lib/utils";

type ApiEnvelope<T> = { success: boolean; data: T };
type PaginatedEnvelope<T> = ApiEnvelope<{
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}>;

export type WarrantyPlan = {
  id: string;
  name: string;
  duration_months: number;
  duration_label: string;
  features: string[] | null;
  price: number;
  currency: string;
};

export type WarrantyPurchase = {
  id: string;
  status: "pending" | "active" | "expired" | "cancelled";
  coverage_starts_at: string | null;
  coverage_ends_at: string | null;
  price_paid: number;
  currency: string;
  plan: {
    name: string | null;
    duration_months: number | null;
    duration_label: string | null;
    features: string[] | null;
  };
  product: {
    name: string | null;
    sku: string | null;
  };
  order_id: string;
  is_claimable: boolean;
  created_at: string;
};

export type WarrantyClaimStatus =
  | "submitted"
  | "under_review"
  | "approved"
  | "rejected"
  | "resolved";

export type WarrantyClaim = {
  id: string;
  claim_number: string;
  status: WarrantyClaimStatus;
  resolution: "repair" | "replace" | "refund" | "no_action" | null;
  issue_type: string;
  issue_description: string;
  purchase_date: string | null;
  warranty_expires_at: string | null;
  covered_by_platform_warranty: boolean;
  evidence_files: string[] | null;
  resolved_at: string | null;
  created_at: string;
  product: { name: string | null; image: string | null };
  vendor: { name: string | null };
};

export type WarrantyClaimDetail = WarrantyClaim & {
  messages: {
    id: string;
    sender_role: "customer" | "vendor" | "admin";
    message: string;
    created_at: string;
  }[];
};

export type IssueType =
  | "defective"
  | "not_working"
  | "physical_damage"
  | "missing_parts"
  | "software_issue"
  | "other";

/** GET /warranty/plans/:orderItemId — available warranty plans for a purchased item */
export async function getWarrantyPlans(orderItemId: string): Promise<WarrantyPlan[]> {
  const envelope = await fetchInstance<ApiEnvelope<WarrantyPlan[]>>(
    `/warranty/plans/${orderItemId}`,
  );
  return envelope.data;
}

/** GET /warranty/purchases — customer's warranty policies */
export async function getWarrantyPurchases(page = 1): Promise<{
  data: WarrantyPurchase[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const envelope = await fetchInstance<PaginatedEnvelope<WarrantyPurchase>>(
    `/warranty/purchases?page=${page}`,
  );
  return envelope.data;
}

/** GET /warranty/claims — customer's warranty claims */
export async function getWarrantyClaims(page = 1): Promise<{
  data: WarrantyClaim[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const envelope = await fetchInstance<PaginatedEnvelope<WarrantyClaim>>(
    `/warranty/claims?page=${page}`,
  );
  return envelope.data;
}

/** POST /warranty/claims — submit a new warranty claim */
export async function submitWarrantyClaim(payload: {
  order_item_id: string;
  issue_type: IssueType;
  issue_description: string;
  evidence_files?: File[];
}): Promise<WarrantyClaim> {
  const fd = new FormData();
  fd.append("order_item_id", payload.order_item_id);
  fd.append("issue_type", payload.issue_type);
  fd.append("issue_description", payload.issue_description);
  payload.evidence_files?.forEach((f) => fd.append("evidence_files[]", f));

  const envelope = await fetchInstance<ApiEnvelope<WarrantyClaim>>(
    "/warranty/claims",
    { method: "POST", body: fd },
  );
  return envelope.data;
}

/** GET /warranty/claims/:claimNumber */
export async function getWarrantyClaimDetail(claimNumber: string): Promise<WarrantyClaimDetail> {
  const envelope = await fetchInstance<ApiEnvelope<WarrantyClaimDetail>>(
    `/warranty/claims/${claimNumber}`,
  );
  return envelope.data;
}

/** POST /warranty/claims/:claimNumber/messages */
export async function addWarrantyClaimMessage(
  claimNumber: string,
  payload: { message: string },
): Promise<void> {
  await fetchInstance(`/warranty/claims/${claimNumber}/messages`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message: payload.message }),
  });
}
