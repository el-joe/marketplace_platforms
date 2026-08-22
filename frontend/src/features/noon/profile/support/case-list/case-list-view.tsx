"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import type { ColumnDef } from "@tanstack/react-table";
import { useRouter } from "@/i18n/navigation";
import CaseListFilter from "./filter";
import CaseListTable from "./table";
import { ALL } from "./constants";

type Props<TRow> = {
  titleKey: string;
  subtitleKey: string;
  data: TRow[];
  buildColumns: (t: (key: string) => string) => ColumnDef<TRow, unknown>[];
  statusOptions: string[];
  secondaryOptions: string[];
  secondaryFilterKey: "priority" | "reason";
  getStatus: (row: TRow) => string;
  getSecondary: (row: TRow) => string;
  getHref: (row: TRow) => string;
};

export default function CaseListView<TRow>({
  data,
  titleKey,
  subtitleKey,
  statusOptions,
  secondaryOptions,
  secondaryFilterKey,
  getStatus,
  getSecondary,
  buildColumns,
  getHref,
}: Props<TRow>) {
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

  const columns = useMemo(() => buildColumns(t), [buildColumns, t]);

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
