import type { ColumnDef } from "@tanstack/react-table";
import { useTranslations } from "next-intl";
import { DataTable } from "@/src/components/ui/data-table";

type Props<TRow> = {
  data: TRow[];
  columns: ColumnDef<TRow, unknown>[];
  header: React.ReactNode;
  onRowClick: (row: TRow) => void;
};

export default function CaseListTable<TRow>({
  data,
  columns,
  header,
  onRowClick,
}: Props<TRow>) {
  const t = useTranslations("support");

  return (
    <DataTable
      data={data}
      columns={columns}
      header={header}
      emptyState={t("noResults")}
      onRowClick={onRowClick}
    />
  );
}
