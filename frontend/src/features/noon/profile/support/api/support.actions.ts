import axiosInstance from "@/src/utils/axiosInstance";
import type {
  DisputeDetails,
  SendMessagePayload,
  SendMessageResult,
  SupportTicketDetails,
} from "../types";

/**
 * Real API integration for this feature, mirroring data.ts's function names
 * 1:1 (getTickets, getTicketById, getDisputes, getDisputeById) so a route's
 * page.tsx can swap `from "./data"` for `from "./api"` without touching
 * anything else.
 */

function toMessageFormData(payload: SendMessagePayload): FormData {
  const formData = new FormData();
  formData.append("message", payload.message);
  if (payload.attachment) formData.append("attachment", payload.attachment);
  return formData;
}

export async function getTickets(): Promise<SupportTicketDetails[]> {
  const { data } = await axiosInstance.get<{ data: SupportTicketDetails[] }>(
    "/support/tickets",
  );
  return data.data;
}

export async function getTicketById(
  id: string,
): Promise<SupportTicketDetails | undefined> {
  try {
    const { data } = await axiosInstance.get<{ data: SupportTicketDetails }>(
      `/support/tickets/${id}`,
    );
    return data.data;
  } catch {
    return undefined;
  }
}

/** POST /support/tickets/:ticket_number/messages */
export async function sendTicketMessage(
  ticketNumber: string,
  payload: SendMessagePayload,
): Promise<SendMessageResult> {
  const { data } = await axiosInstance.post<{ data: SendMessageResult }>(
    `/support/tickets/${ticketNumber}/messages`,
    toMessageFormData(payload),
  );
  return data.data;
}

export async function getDisputes(): Promise<DisputeDetails[]> {
  const { data } = await axiosInstance.get<{ data: DisputeDetails[] }>(
    "/disputes",
  );
  return data.data;
}

export async function getDisputeById(
  id: string,
): Promise<DisputeDetails | undefined> {
  try {
    const { data } = await axiosInstance.get<{ data: DisputeDetails }>(
      `/disputes/${id}`,
    );
    return data.data;
  } catch {
    return undefined;
  }
}

/** POST /disputes/:dispute_number/messages */
export async function sendDisputeMessage(
  disputeNumber: string,
  payload: SendMessagePayload,
): Promise<SendMessageResult> {
  const { data } = await axiosInstance.post<{ data: SendMessageResult }>(
    `/disputes/${disputeNumber}/messages`,
    toMessageFormData(payload),
  );
  return data.data;
}
