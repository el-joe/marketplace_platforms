import { fetchInstance } from "@/src/lib/utils";

export interface Receiver {
  id: string;
  name: string;
  phone: string;
  is_default: boolean;
}

export interface CreateReceiverPayload {
  name: string;
  phone: string;
  is_default?: boolean;
}

/**
 * Lists the authenticated customer's saved receivers, most-recently-set-default first.
 * GET /receivers
 */
export async function getReceivers(): Promise<Receiver[]> {
  const res = await fetchInstance<{
    success: boolean;
    message?: string;
    data: Receiver[];
  }>("/receivers");
  return res?.data ?? [];
}

/**
 * Creates a new receiver.
 * POST /receivers
 */
export async function createReceiver(
  payload: CreateReceiverPayload,
): Promise<Receiver> {
  const res = await fetchInstance<{
    success: boolean;
    message?: string;
    data: Receiver;
  }>("/receivers", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return res.data;
}

/**
 * Marks this receiver as the customer's default.
 * PUT /receivers/:receiverId/set-default
 */
export async function setDefaultReceiver(
  receiverId: string,
): Promise<Receiver> {
  const res = await fetchInstance<{
    success: boolean;
    message?: string;
    data: Receiver;
  }>(`/receivers/${receiverId}/set-default`, {
    method: "PUT",
  });
  return res.data;
}
