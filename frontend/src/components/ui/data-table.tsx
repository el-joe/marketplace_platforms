"use client";

import * as React from "react";
import {
  type ColumnDef,
  flexRender,
  getCoreRowModel,
  getSortedRowModel,
  useReactTable,
  type SortingState,
} from "@tanstack/react-table";
import { ChevronUpIcon, ChevronDownIcon, ChevronsUpDownIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from "@/src/components/ui/table";
import { cn } from "@/src/lib/utils";

type DataTableProps<TData> = {
  data: TData[];
  columns: ColumnDef<TData, unknown>[];
  /** Custom filter/actions bar rendered above the table. */
  header?: React.ReactNode;
  onRowClick?: (row: TData) => void;
  emptyState?: React.ReactNode;
  className?: string;
};

export function DataTable<TData>({
  data,
  columns,
  header,
  onRowClick,
  emptyState,
  className,
}: DataTableProps<TData>) {
  const [sorting, setSorting] = React.useState<SortingState>([]);

  const table = useReactTable({
    data,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  });

  return (
    <div className={cn("flex flex-col gap-4", className)}>
      {header}

      <Card className="border border-border overflow-hidden p-0">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className="hover:bg-transparent">
                {headerGroup.headers.map((header) => {
                  const canSort = header.column.getCanSort();
                  const sortDirection = header.column.getIsSorted();
                  return (
                    <TableHead key={header.id}>
                      {header.isPlaceholder ? null : canSort ? (
                        <button
                          type="button"
                          onClick={header.column.getToggleSortingHandler()}
                          className="flex items-center gap-1 cursor-pointer select-none hover:text-primary"
                        >
                          {flexRender(
                            header.column.columnDef.header,
                            header.getContext(),
                          )}
                          {sortDirection === "asc" ? (
                            <ChevronUpIcon className="size-3.5" />
                          ) : sortDirection === "desc" ? (
                            <ChevronDownIcon className="size-3.5" />
                          ) : (
                            <ChevronsUpDownIcon className="size-3.5 opacity-40" />
                          )}
                        </button>
                      ) : (
                        flexRender(
                          header.column.columnDef.header,
                          header.getContext(),
                        )
                      )}
                    </TableHead>
                  );
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows.length === 0 ? (
              <TableEmpty colSpan={columns.length}>
                {emptyState ?? "No results"}
              </TableEmpty>
            ) : (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  data-clickable={!!onRowClick}
                  onClick={() => onRowClick?.(row.original)}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </Card>
    </div>
  );
}
