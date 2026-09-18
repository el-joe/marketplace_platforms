import axiosInstance from "@/src/utils/axiosInstance";

export type Receiver = {
  id: string;
  name: string;
  phone: string;
  is_default: boolean;
};

/** Shared: list the authenticated customer's saved receivers. GET /receivers */
export async function getReceivers(): Promise<Receiver[]> {
  const { data } = await axiosInstance.get<{ data: Receiver[] }>("/receivers");
  return data.data;
}
