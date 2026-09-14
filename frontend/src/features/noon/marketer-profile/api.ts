import { MarketerProfileData } from "./helpers/types";
import { region } from "@/src/utils/region";

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? "/api/public/v1";

export class MarketerProfileNotFoundError extends Error {}

export async function getMarketerProfile(slug: string): Promise<MarketerProfileData> {
  const res = await fetch(`${PUBLIC_BASE}/${region}/marketers/${slug}`, {
    cache: "no-store",
    headers: { Accept: "application/json" },
  });

  if (res.status === 404) {
    throw new MarketerProfileNotFoundError();
  }
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }

  const body: { data: MarketerProfileData } = await res.json();
  return body.data;
}

export interface MarketerListingsParams {
  slug: string;
  ownPage?: number;
  campaignPage?: number;
  perPage?: number;
}

/** Fetches only the listings portion (uncached, auth-aware for wishlist). */
export async function getMarketerListings(
  params: MarketerListingsParams
): Promise<Pick<MarketerProfileData, "own_listings" | "campaign_listings">> {
  const { slug, ownPage = 1, campaignPage = 1, perPage = 12 } = params;
  const qs = new URLSearchParams({
    own_page: String(ownPage),
    campaign_page: String(campaignPage),
    per_page: String(perPage),
  });
  const res = await fetch(`${PUBLIC_BASE}/${region}/marketers/${slug}?${qs}`, {
    cache: "no-store",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const body: { data: MarketerProfileData } = await res.json();
  return {
    own_listings: body.data.own_listings,
    campaign_listings: body.data.campaign_listings,
  };
}
