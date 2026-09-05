import { MarketerProfileData } from "./helpers/types";

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? "/api/public/v1";

export class MarketerProfileNotFoundError extends Error {}

export async function getMarketerProfile(slug: string): Promise<MarketerProfileData> {
  const res = await fetch(`${PUBLIC_BASE}/marketers/${slug}`, {
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
