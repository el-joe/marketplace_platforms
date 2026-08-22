import { fetchInstance } from "@/src/lib/utils";
import type { ApiEnvelope, ClassifiedListing } from "../../browse/helpers/types";

/** GET /listings/classified/{slug}/similar — same category listings, excludes current */
export async function getSimilarListings(
  slug: string,
): Promise<ClassifiedListing[]> {
  try {
    const envelope = await fetchInstance<
      ApiEnvelope<{ items: ClassifiedListing[] }>
    >(`/listings/classified/${slug}/similar`);
    return envelope.data?.items ?? [];
  } catch {
    return [];
  }
}
