export type SenderRole = "customer" | "support";

export type Attachment = {
  url: string;
  name: string;
};

export type CaseMessage = {
  id: string | number;
  sender_role: SenderRole;
  message: string;
  created_at: string;
  attachments: Attachment[];
};

export type TicketStatus = "open" | "in_progress" | "resolved" | "closed";
export type TicketPriority = "low" | "normal" | "high" | "urgent";

export type SupportTicket = {
  id: number;
  ticket_number: string;
  category: string;
  priority: TicketPriority;
  status: TicketStatus;
  subject: string;
  created_at: string;
  resolved_at: string | null;
  satisfaction_rating: number | null;
  satisfaction_comment: string | null;
};

export type SupportTicketDetails = SupportTicket & {
  messages: CaseMessage[];
};

export type DisputeStatus =
  | "under_review"
  | "resolved"
  | "rejected"
  | "escalated";

export type Dispute = {
  id: string;
  dispute_number: string;
  order_number: string;
  reason: string;
  description: string;
  status: DisputeStatus;
  resolution: string | null;
  resolution_notes: string | null;
  compensation: string | null;
  resolved_at: string | null;
  created_at: string;
};

export type DisputeDetails = Dispute & {
  messages: CaseMessage[];
};

export type SendMessagePayload = {
  message: string;
  attachment?: File;
};

export type SendMessageResult = {
  id: string | number;
  message: string;
  created_at: string;
};

export type CaseMode = "ticket" | "dispute";

/** Discriminated union covering both list-row shapes. */
export type CaseListItem =
  | ({ mode: "ticket" } & SupportTicket)
  | ({ mode: "dispute" } & Dispute);

/** Discriminated union covering both detail-page shapes. */
export type CaseDetails =
  | ({ mode: "ticket" } & SupportTicketDetails)
  | ({ mode: "dispute" } & DisputeDetails);

/**
 * Normalized view of a CaseDetails used by the parts of the UI that don't
 * care whether they're rendering a ticket or a dispute (page header, message
 * timeline, reply form). Mode-specific fields are still read directly off
 * the discriminated union where the UI needs to adapt (see CaseInfo).
 */
export type CaseSummary = {
  id: string | number;
  number: string;
  title: string;
  status: string;
  createdAt: string;
  resolvedAt: string | null;
  messages: CaseMessage[];
};

export function summarizeCase(item: CaseDetails): CaseSummary {
  if (item.mode === "ticket") {
    return {
      id: item.id,
      number: item.ticket_number,
      title: item.subject,
      status: item.status,
      createdAt: item.created_at,
      resolvedAt: item.resolved_at,
      messages: item.messages,
    };
  }
  return {
    id: item.id,
    number: item.dispute_number,
    title: item.description,
    status: item.status,
    createdAt: item.created_at,
    resolvedAt: item.resolved_at,
    messages: item.messages,
  };
}
