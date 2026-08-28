import { fetchInstance } from "@/src/lib/utils";

type ApiEnvelope<T> = { success: boolean; data: T };

export type WarrantyPlan = {
  id: string;
  name: string;
  duration_months: number;
  price: number;
  currency: string;
  coverage_description: string;
};

export type WarrantyPurchase = {
  id: string;
  plan_name: string;
  product_name: string | null;
  starts_at: string;
  expires_at: string;
  status: "active" | "expired" | "claimed";
  price: number;
  currency: string;
};

export type WarrantyClaimStatus = "open" | "in_progress" | "resolved" | "rejected";

export type WarrantyClaim = {
  id: string;
  claim_number: string;
  product_name: string | null;
  issue_description: string;
  status: WarrantyClaimStatus;
  resolution_notes: string | null;
  created_at: string;
};

export type WarrantyClaimDetail = WarrantyClaim & {
  messages: {
    id: string;
    sender_role: "customer" | "support";
    message: string;
    created_at: string;
    attachments: { url: string; name: string }[];
  }[];
};

/** GET /warranty/plans/:orderItemId — available warranty plans for a purchased item */
export async function getWarrantyPlans(orderItemId: string): Promise<WarrantyPlan[]> {
  const envelope = await fetchInstance<ApiEnvelope<WarrantyPlan[]>>(
    `/warranty/plans/${orderItemId}`,
  );
  return envelope.data;
}

/** GET /warranty/purchases — customer's active warranty policies */
export async function getWarrantyPurchases(page = 1): Promise<{
  items: WarrantyPurchase[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const envelope = await fetchInstance<ApiEnvelope<{
    items: WarrantyPurchase[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }>>(`/warranty/purchases?page=${page}`);
  return envelope.data;
}

/** GET /warranty/claims — customer's warranty claims */
export async function getWarrantyClaims(page = 1): Promise<{
  items: WarrantyClaim[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const envelope = await fetchInstance<ApiEnvelope<{
    items: WarrantyClaim[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }>>(`/warranty/claims?page=${page}`);
  return envelope.data;
}

/** POST /warranty/claims — submit a new warranty claim */
export async function submitWarrantyClaim(payload: {
  order_item_id: string;
  issue_description: string;
  attachments?: File[];
}): Promise<WarrantyClaim> {
  const fd = new FormData();
  fd.append("order_item_id", payload.order_item_id);
  fd.append("issue_description", payload.issue_description);
  payload.attachments?.forEach((f) => fd.append("attachments[]", f));

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
  payload: { message: string; attachment?: File },
): Promise<void> {
  const fd = new FormData();
  fd.append("message", payload.message);
  if (payload.attachment) fd.append("attachment", payload.attachment);
  await fetchInstance(`/warranty/claims/${claimNumber}/messages`, {
    method: "POST",
    body: fd,
  });
}
