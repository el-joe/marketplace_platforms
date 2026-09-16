import { ISellerProfile } from "./types";
import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";

export class SellerNotFoundError extends Error {}

export async function getSellerProfile(sellerId: string): Promise<ISellerProfile> {
  const res = await fetch(`${apiPublicBaseUrlGlobal}/sellers/${sellerId}`, {
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
