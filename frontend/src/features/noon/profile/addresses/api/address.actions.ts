import axiosInstance from "@/src/utils/axiosInstance";
import type { Address } from "@/src/services/address";

/** Feature-only: permanently deletes an address. DELETE /addresses/:address */
export async function deleteAddress(id: string): Promise<void> {
  await axiosInstance.delete(`/addresses/${id}`);
}

/** Feature-only: marks an address as the customer's default. PUT /addresses/:address/set-default */
export async function setDefaultAddress(id: string): Promise<Address> {
  const { data } = await axiosInstance.put<{ data: Address }>(
    `/addresses/${id}/set-default`,
  );
  return data.data;
}
