import { fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  ClassifiedDetail,
  InquiryPayload,
} from "../helpers/types";

/** GET /listings/classified/{slug} — classified listing detail */
export async function getClassifiedListing(
  slug: string,
): Promise<ClassifiedDetail> {
  const envelope = await fetchInstance<ApiEnvelope<ClassifiedDetail>>(
    `/listings/classified/${slug}`,
  );
  return envelope.data;
}

/** POST /listings/classified/{slug}/inquiries — send an inquiry to the seller */
export async function sendInquiry(
  slug: string,
  payload: InquiryPayload,
): Promise<{ id: string }> {
  const envelope = await fetchInstance<ApiEnvelope<{ id: string }>>(
    `/listings/classified/${slug}/inquiries`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}
