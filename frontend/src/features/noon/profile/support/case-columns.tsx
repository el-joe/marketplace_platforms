import { format } from "date-fns";
import type { ColumnDef } from "@tanstack/react-table";
import { Badge } from "@/src/components/ui/badge";
import type { Dispute, SupportTicket } from "./types";
import { priorityVariant, statusVariant } from "./case-status";

type Translate = (key: string) => string;

export function createTicketColumns(t: Translate): ColumnDef<SupportTicket>[] {
  return [
    {
      accessorKey: "ticket_number",
      header: t("columns.number"),
      cell: (info) => (
        <span className="font-semibold text-primary">
          {info.getValue<string>()}
        </span>
      ),
    },
    {
      accessorKey: "subject",
      header: t("columns.subject"),
      cell: (info) => (
        <span className="max-w-70 truncate block">{info.getValue<string>()}</span>
      ),
    },
    {
      accessorKey: "category",
      header: t("columns.category"),
      cell: (info) => t(`category.${info.getValue<string>()}`),
    },
    {
      accessorKey: "priority",
      header: t("columns.priority"),
      cell: (info) => {
        const priority = info.getValue<string>();
        return (
          <Badge variant={priorityVariant(priority)}>
            {t(`priority.${priority}`)}
          </Badge>
        );
      },
    },
    {
      accessorKey: "status",
      header: t("columns.status"),
      cell: (info) => {
        const status = info.getValue<string>();
        return (
          <Badge variant={statusVariant(status)}>{t(`status.${status}`)}</Badge>
        );
      },
    },
    {
      accessorKey: "created_at",
      header: t("columns.created"),
      cell: (info) => format(new Date(info.getValue<string>()), "MMM d, yyyy"),
    },
  ];
}

export function createDisputeColumns(t: Translate): ColumnDef<Dispute>[] {
  return [
    {
      accessorKey: "dispute_number",
      header: t("columns.number"),
      cell: (info) => (
        <span className="font-semibold text-primary">
          {info.getValue<string>()}
        </span>
      ),
    },
    {
      accessorKey: "order_number",
      header: t("columns.order"),
    },
    {
      accessorKey: "description",
      header: t("columns.description"),
      cell: (info) => (
        <span className="max-w-70 truncate block">{info.getValue<string>()}</span>
      ),
    },
    {
      accessorKey: "reason",
      header: t("columns.reason"),
      cell: (info) => t(`reason.${info.getValue<string>()}`),
    },
    {
      accessorKey: "status",
      header: t("columns.status"),
      cell: (info) => {
        const status = info.getValue<string>();
        return (
          <Badge variant={statusVariant(status)}>{t(`status.${status}`)}</Badge>
        );
      },
    },
    {
      accessorKey: "created_at",
      header: t("columns.created"),
      cell: (info) => format(new Date(info.getValue<string>()), "MMM d, yyyy"),
    },
  ];
}
