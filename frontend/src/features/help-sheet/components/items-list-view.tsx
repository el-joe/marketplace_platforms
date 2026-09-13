"use client";

import { useMemo, useState } from "react";
import { ChevronDownIcon, PackageXIcon, SearchIcon } from "lucide-react";
import { Input } from "@/src/components/ui/base-inputs/input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
import HelpSheetHeader from "./help-sheet-header";
import type { HelpListItem, HelpNode } from "../types";

interface ItemsListViewProps {
  node: HelpNode;
  onBack: () => void;
  onClose: () => void;
}

const DURATION_OPTIONS = [
  "Last 3 months",
  "Last 6 months",
  "Last 12 months",
] as const;

type StatusTab = HelpListItem["status"];

export default function ItemsListView({
  node,
  onBack,
  onClose,
}: ItemsListViewProps) {
  const [statusTab, setStatusTab] = useState<StatusTab>("in-progress");
  const [duration, setDuration] = useState<string>(DURATION_OPTIONS[0]);
  const [search, setSearch] = useState("");

  const items = useMemo(() => {
    const bySearch = (node.listItems ?? []).filter((item) =>
      item.title.toLowerCase().includes(search.trim().toLowerCase()),
    );
    return bySearch.filter((item) => item.status === statusTab);
  }, [node.listItems, statusTab, search]);

  return (
    <div className="flex h-full flex-col">
      <HelpSheetHeader
        title="Choose applicable items"
        showBack
        onBack={onBack}
        onClose={onClose}
      />

      <div className="flex flex-col gap-4 px-6 pb-4">
        <div className="flex justify-center gap-2">
          <button
            type="button"
            onClick={() => setStatusTab("in-progress")}
            className={
              "cursor-pointer rounded-full px-4 py-2 text-sm font-semibold transition-colors " +
              (statusTab === "in-progress"
                ? "bg-[#101628] text-white"
                : "bg-gray-3 text-light")
            }
          >
            In progress
          </button>
          <button
            type="button"
            onClick={() => setStatusTab("completed")}
            className={
              "cursor-pointer rounded-full px-4 py-2 text-sm font-semibold transition-colors " +
              (statusTab === "completed"
                ? "bg-[#101628] text-white"
                : "bg-gray-3 text-light")
            }
          >
            Completed
          </button>
        </div>

        <div className="flex items-center gap-2">
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search Items"
            startIcon={<SearchIcon className="size-4 text-gray" />}
            className="h-11 rounded-xl bg-white"
          />
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <button
                  type="button"
                  className="flex h-11 shrink-0 cursor-pointer items-center gap-1 whitespace-nowrap rounded-xl bg-white px-3 text-sm font-semibold text-light shadow-sm"
                >
                  {duration}
                  <ChevronDownIcon className="size-4 text-gray" />
                </button>
              }
            />
            <DropdownMenuContent>
              {DURATION_OPTIONS.map((option) => (
                <DropdownMenuItem
                  key={option}
                  onClick={() => setDuration(option)}
                >
                  {option}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-6 pb-6">
        {items.length === 0 ? (
          <div className="flex h-full flex-col items-center justify-center gap-4 text-center">
            <span className="flex size-20 items-center justify-center rounded-full bg-gray-3">
              <PackageXIcon className="size-9 text-gray" />
            </span>
            <div>
              <p className="font-bold text-light">No items found</p>
              <p className="mt-1 text-sm text-gray">
                We couldn&apos;t find any items that matched your search in
                the given time period
              </p>
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {items.map((item) => (
              <div
                key={item.id}
                className="rounded-2xl bg-white p-4 shadow-sm"
              >
                <p className="font-bold text-light">{item.title}</p>
                <p className="text-sm text-gray">{item.subtitle}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
