"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import type { ColumnDef } from "@tanstack/react-table";
import { useRouter } from "@/i18n/navigation";
import CaseListFilter from "./filter";
import CaseListTable from "./table";
import {
  ALL,
  DISPUTE_REASONS,
  DISPUTE_STATUSES,
  TICKET_PRIORITIES,
  TICKET_STATUSES,
} from "./constants";
import { createDisputeColumns, createTicketColumns } from "../case-columns";
import type { Dispute, SupportTicket } from "../types";

type BaseProps<TRow> = {
  titleKey: string;
  subtitleKey: string;
  data: TRow[];
  columns: ColumnDef<TRow, unknown>[];
  statusOptions: string[];
  secondaryOptions: string[];
  secondaryFilterKey: "priority" | "reason";
  getStatus: (row: TRow) => string;
  getSecondary: (row: TRow) => string;
  getHref: (row: TRow) => string;
};

/**
 * Mode-agnostic table/filter shell. Stays private to this file — the
 * mode -> config mapping (which columns, which getters, which route) lives
 * entirely in `CaseListView` below, so nothing crosses back out to a server
 * parent as a function prop.
 */
function CaseListViewBase<TRow>({
  data,
  titleKey,
  subtitleKey,
  statusOptions,
  secondaryOptions,
  secondaryFilterKey,
  getStatus,
  getSecondary,
  columns,
  getHref,
}: BaseProps<TRow>) {
  const t = useTranslations("support");
  const router = useRouter();

  const [status, setStatus] = useState<string>(ALL);
  const [secondary, setSecondary] = useState<string>(ALL);

  const filtered = useMemo(
    () =>
      data.filter((row) => {
        const statusMatch = status === ALL || getStatus(row) === status;
        const secondaryMatch =
          secondary === ALL || getSecondary(row) === secondary;
        return statusMatch && secondaryMatch;
      }),
    [data, status, secondary, getStatus, getSecondary],
  );

  return (
    <div>
      <h1 className="text-[28px] font-bold text-primary">{t(titleKey)}</h1>
      <p className="text-sm text-gray mt-1">{t(subtitleKey)}</p>

      <div className="mt-6">
        <CaseListTable
          data={filtered}
          columns={columns}
          onRowClick={(row) => router.push(getHref(row))}
          header={
            <CaseListFilter
              statusValue={status}
              onStatusChange={setStatus}
              statusOptions={statusOptions}
              secondaryValue={secondary}
              onSecondaryChange={setSecondary}
              secondaryOptions={secondaryOptions}
              secondaryFilterKey={secondaryFilterKey}
            />
          }
        />
      </div>
    </div>
  );
}

type Props =
  | { mode: "ticket"; data: SupportTicket[] }
  | { mode: "dispute"; data: Dispute[] };

export default function CaseListView(props: Props) {
  const t = useTranslations("support");

  if (props.mode === "ticket") {
    return (
      <CaseListViewBase
        titleKey="ticketsTitle"
        subtitleKey="ticketsSubtitle"
        data={props.data}
        columns={createTicketColumns(t)}
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
    <CaseListViewBase
      titleKey="disputesTitle"
      subtitleKey="disputesSubtitle"
      data={props.data}
      columns={createDisputeColumns(t)}
      statusOptions={DISPUTE_STATUSES}
      secondaryOptions={DISPUTE_REASONS}
      secondaryFilterKey="reason"
      getStatus={(row) => row.status}
      getSecondary={(row) => row.reason}
      getHref={(row) => `/disputes/${row.id}`}
    />
  );
}
