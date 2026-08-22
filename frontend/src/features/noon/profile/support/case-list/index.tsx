import CaseListView from "./case-list-view";
import {
  DISPUTE_REASONS,
  DISPUTE_STATUSES,
  TICKET_PRIORITIES,
  TICKET_STATUSES,
} from "./constants";
import { createDisputeColumns, createTicketColumns } from "../case-columns";
import type { Dispute, SupportTicket } from "../types";

type Props =
  | { mode: "ticket"; data: SupportTicket[] }
  | { mode: "dispute"; data: Dispute[] };

export default function CaseListPage(props: Props) {
  if (props.mode === "ticket") {
    return (
      <CaseListView
        titleKey="ticketsTitle"
        subtitleKey="ticketsSubtitle"
        data={props.data}
        buildColumns={createTicketColumns}
        statusOptions={TICKET_STATUSES}
        secondaryOptions={TICKET_PRIORITIES}
        secondaryFilterKey="priority"
        getStatus={(row) => row.status}
        getSecondary={(row) => row.priority}
        getHref={(row) => `/tickets/${row.id}`}
      />
    );
  }

  return (
    <CaseListView
      titleKey="disputesTitle"
      subtitleKey="disputesSubtitle"
      data={props.data}
      buildColumns={createDisputeColumns}
      statusOptions={DISPUTE_STATUSES}
      secondaryOptions={DISPUTE_REASONS}
      secondaryFilterKey="reason"
      getStatus={(row) => row.status}
      getSecondary={(row) => row.reason}
      getHref={(row) => `/disputes/${row.id}`}
    />
  );
}
