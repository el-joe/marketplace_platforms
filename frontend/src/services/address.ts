import axiosInstance from "@/src/utils/axiosInstance";

export type AddressType = "work" | "home" | "other";

export type Address = {
  id: string;
  label: string | null;
  recipient_name: string;
  recipient_phone: string;
  country_id: string;
  city_id: string;
  area: string | null;
  street_address: string;
  building: string | null;
  floor: string | null;
  apartment: string | null;
  postal_code: string | null;
  landmark: string | null;
  latitude: number | null;
  longitude: number | null;
  is_default: boolean;
  address_type: AddressType;
  full_address: string;
};

export type AddressPayload = {
  label?: string | null;
  recipient_name: string;
  recipient_phone: string;
  city_id: string;
  area?: string | null;
  street_address: string;
  building?: string | null;
  floor?: string | null;
  apartment?: string | null;
  landmark?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  address_type: AddressType;
  is_default?: boolean;
};

/** Shared: list the authenticated customer's saved addresses. GET /addresses */
export async function getAddresses(): Promise<Address[]> {
  const { data } = await axiosInstance.get<{ data: Address[] }>("/addresses");
  return data.data;
}

/** Shared: create a new address. POST /addresses */
export async function createAddress(payload: AddressPayload): Promise<Address> {
  const { data } = await axiosInstance.post<{ data: Address }>(
    "/addresses",
    payload,
  );
  return data.data;
}

/** Shared: update an existing address (full re-validation, not partial). PUT /addresses/:address */
export async function updateAddress(
  id: string,
  payload: AddressPayload,
): Promise<Address> {
  const { data } = await axiosInstance.put<{ data: Address }>(
    `/addresses/${id}`,
    payload,
  );
  return data.data;
}
