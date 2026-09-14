import { ISellerProfile } from "./types";

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? "/api/public/v1";

export class SellerNotFoundError extends Error {}

export async function getSellerProfile(sellerId: string): Promise<ISellerProfile> {
  const res = await fetch(`${PUBLIC_BASE}/sellers/${sellerId}`, {
    next: { revalidate: 300 },
    headers: { Accept: "application/json" },
  });

  if (res.status === 404) {
    throw new SellerNotFoundError();
  }
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }

  const body: { data: ISellerProfile } = await res.json();
  return body.data;
}
