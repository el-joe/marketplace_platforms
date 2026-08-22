import type { ICustomerProfile } from "@/types";
import { fetchInstance } from "@/src/lib/utils";

export type UpdateProfilePayload = {
  name: string;
  date_of_birth?: string | null;
  phone?: string | null;
};

/** Shared: read the authenticated customer's profile. GET /profile */
export async function getProfile(): Promise<ICustomerProfile> {
  const { data } = await fetchInstance<{ data: ICustomerProfile }>("/profile");
  return data;
}

/** Shared: update the authenticated customer's profile. PUT /profile */
export async function updateProfile(
  payload: UpdateProfilePayload,
): Promise<ICustomerProfile> {
  const { data } = await fetchInstance<{ data: ICustomerProfile }>("/profile", {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  return data;
}
