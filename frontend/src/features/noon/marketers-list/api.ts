export interface MarketerCard {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate";
  profile_slug: string;
  profile_url: string;
  banner_url: string | null;
  total_campaigns: number;
  total_conversions: number;
  avatar_initial: string;
}

export interface MarketerListResult {
  items: MarketerCard[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? "/api/public/v1";

export async function getMarketersList(params: { type?: string; page?: number } = {}): Promise<MarketerListResult> {
  const query = new URLSearchParams();
  if (params.type) query.set("type", params.type);
  if (params.page) query.set("page", String(params.page));
  query.set("per_page", "24");

  const res = await fetch(`${PUBLIC_BASE}/marketers?${query.toString()}`, {
    cache: "no-store",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    return { items: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 0 } };
  }

  const body: { data: MarketerListResult } = await res.json();
  return body.data;
}
