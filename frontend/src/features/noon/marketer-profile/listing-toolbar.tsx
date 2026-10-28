"use client";
import {
  ArrowUpDownIcon,
  CheckIcon,
  SlidersHorizontalIcon,
  XIcon,
} from "lucide-react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import useLocale from "@/src/hooks/use-locale";
import { cn } from "@/src/lib/utils";
import FilterSidebar from "./filters";
import { Facets, ProfileKind } from "./helpers/types";
import useMarketerFilter from "./helpers/use-marketer-filter";

const SORT_OPTIONS = [
  { value: "popular", labelKey: "sortPopular" },
  { value: "newest", labelKey: "sortNewest" },
  { value: "price_asc", labelKey: "sortPriceAsc" },
  { value: "price_desc", labelKey: "sortPriceDesc" },
] as const;

type Props = {
  facets: Facets;
  kind: ProfileKind;
  total: number;
};

const useSort = () => {
  const searchParams = useSearchParams();
  const { setFilter } = useMarketerFilter();
  const current = searchParams.get("sort") ?? "popular";
  // "popular" is the default, so it needs no param in the URL.
  const setSort = (value: string) =>
    setFilter("sort", value === "popular" ? "" : value);
  return { current, setSort };
};

export default function ListingToolbar({ facets, kind, total }: Props) {
  const t = useTranslations("marketerProfile");
  const locale = useLocale();
  const { current, setSort } = useSort();
  const filtersSide = locale === "ar" ? "right" : "left";

  return (
    <>
      {/* mobile: one bordered bar, Sort | Filter */}
      <div className="mb-4 grid grid-cols-2 divide-x divide-border-color rounded-lg border border-border-color bg-white lg:hidden rtl:divide-x-reverse">
        <Sheet>
          <SheetTrigger
            render={
              <button
                type="button"
                className="flex items-center justify-center gap-2 py-3 text-sm"
              />
            }
          >
            <ArrowUpDownIcon className="size-4" />
            {t("sortLabel")}
          </SheetTrigger>
          <SheetContent side="bottom" showCloseButton={false} className="p-4">
            <SheetHeader className="flex-row items-center justify-between p-0">
              <SheetTitle>{t("sortBy")}</SheetTitle>
              <SheetClose render={<Button variant="ghost" size="icon-sm" />}>
                <XIcon />
                <span className="sr-only">{t("close")}</span>
              </SheetClose>
            </SheetHeader>
            <div className="flex flex-col">
              {SORT_OPTIONS.map((o) => (
                <SheetClose
                  key={o.value}
                  render={
                    <button
                      type="button"
                      onClick={() => setSort(o.value)}
                      className={cn(
                        "flex items-center justify-between rounded-md px-2 py-3 text-start text-sm",
                        current === o.value && "bg-gray-2 font-bold",
                      )}
                    />
                  }
                >
                  {t(o.labelKey)}
                  {current === o.value && <CheckIcon className="size-4" />}
                </SheetClose>
              ))}
            </div>
          </SheetContent>
        </Sheet>

        <Sheet>
          <SheetTrigger
            render={
              <button
                type="button"
                className="flex items-center justify-center gap-2 py-3 text-sm"
              />
            }
          >
            <SlidersHorizontalIcon className="size-4" />
            {t("filterLabel")}
          </SheetTrigger>
          <SheetContent
            side={filtersSide}
            showCloseButton={false}
            className="overflow-y-auto p-4"
          >
            <SheetHeader className="sticky top-0 z-10 flex-row items-center justify-between bg-popover p-0">
              <SheetTitle>{t("filtersTitle")}</SheetTitle>
              <SheetClose render={<Button variant="ghost" size="icon-sm" />}>
                <XIcon />
                <span className="sr-only">{t("close")}</span>
              </SheetClose>
            </SheetHeader>
            <FilterSidebar facets={facets} kind={kind} />
          </SheetContent>
        </Sheet>
      </div>

      {/* desktop: result count + sort select */}
      <div className="mb-4 hidden items-center justify-between lg:flex">
        <p className="text-sm text-gray">
          {t("resultsCount", { count: total })}
        </p>
        <label className="flex items-center gap-2 text-sm">
          {t("sortBy")}
          <select
            value={current}
            onChange={(e) => setSort(e.target.value)}
            className="rounded-md border border-border-color bg-white px-3 py-1.5 outline-none focus:border-main"
          >
            {SORT_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {t(o.labelKey)}
              </option>
            ))}
          </select>
        </label>
      </div>
    </>
  );
}
