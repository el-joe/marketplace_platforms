"use client";
import { PlaneIcon, ShoppingBagIcon, StoreIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";
import { Facets, ProfileKind } from "./helpers/types";
import useMarketerFilter from "./helpers/use-marketer-filter";

const TABS = [
  { kind: "products", labelKey: "tabProducts", Icon: ShoppingBagIcon },
  { kind: "classified", labelKey: "tabClassified", Icon: StoreIcon },
  { kind: "travel", labelKey: "tabTravel", Icon: PlaneIcon },
] as const;

// Filters belong to a tab, so they are dropped when switching tabs (sort and search are kept).
const TAB_SCOPED_FILTERS = [
  "source",
  "category",
  "brand",
  "destination",
  "price_min",
  "price_max",
  "discount",
];

type Props = {
  active: ProfileKind;
  kinds: Facets["kinds"];
};

export default function ListingTabs({ active, kinds }: Props) {
  const t = useTranslations("marketerProfile");
  const { setFilters } = useMarketerFilter();
  const counts = Object.fromEntries(kinds.map((k) => [k.key, k.count]));
  return (
    <div
      role="tablist"
      className="mb-3 flex overflow-x-auto border-b border-border-color scrollbar-hide"
    >
      {TABS.map(({ kind, labelKey, Icon }) => {
        const isActive = kind === active;
        return (
          <button
            key={kind}
            type="button"
            role="tab"
            aria-selected={isActive}
            onClick={() =>
              !isActive &&
              setFilters([
                ["kind", kind],
                ...TAB_SCOPED_FILTERS.map((k): [string, string] => [k, ""]),
              ])
            }
            className={cn(
              "-mb-px flex min-w-max flex-1 items-center justify-center gap-1.5 border-b-2 px-4 py-3 text-sm transition-colors",
              isActive
                ? "border-primary font-bold text-primary"
                : "border-transparent text-gray hover:text-primary",
            )}
          >
            <Icon className="size-4" />
            {t(labelKey)}
            <span className="text-xs text-gray">{counts[kind] ?? 0}</span>
          </button>
        );
      })}
    </div>
  );
}
