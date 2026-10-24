"use client";
import { PackageSearch } from "lucide-react";
import { Spinner } from "@/src/components/ui/spinner";
import useLocale from "@/src/hooks/use-locale";
import { useLoadMore } from "../helpers/use-load-more";
import type { MarketerFilters, MarketerListings } from "../helpers/types";
import MarketerListingCard from "./marketer-listing-card";

interface Props {
  slug: string;
  filters: MarketerFilters;
  initial: MarketerListings;
  labels: {
    empty: string;
    emptyHint: string;
    loadMore: string;
    retry: string;
    exclusive: string;
    negotiable: string;
    classified: string;
  };
}

/** 4 cols desktop / 2 mobile. Remount (key) on filter change resets pagination. */
export default function MarketerListingsGrid({ slug, filters, initial, labels }: Props) {
  const locale = useLocale();
  const { items, loading, failed, hasMore, loadMore, sentinelRef } = useLoadMore(slug, filters, initial);

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 py-16 text-center">
        <div className="flex size-14 items-center justify-center rounded-full bg-gray-2">
          <PackageSearch className="size-6 text-gray" />
        </div>
        <p className="text-sm font-semibold text-primary">{labels.empty}</p>
        <p className="text-xs text-gray">{labels.emptyHint}</p>
      </div>
    );
  }

  return (
    <>
      <div className="grid grid-cols-2 gap-x-3 gap-y-6 lg:grid-cols-3 lg:gap-x-4 xl:grid-cols-4">
        {items.map((item) => (
          <MarketerListingCard
            key={item.kind === "classified" ? `c-${item.id}` : `p-${item.listing_id}`}
            item={item}
            locale={locale}
            labels={labels}
          />
        ))}
      </div>

      <div ref={sentinelRef} className="h-4" />
      {loading && (
        <div className="flex justify-center py-6">
          <Spinner />
        </div>
      )}
      {hasMore && !loading && (
        <div className="flex justify-center py-6">
          <button
            type="button"
            onClick={() => void loadMore()}
            className="rounded-full border border-border-color px-6 py-2 text-sm font-semibold text-primary transition hover:bg-gray-2"
          >
            {failed ? labels.retry : labels.loadMore}
          </button>
        </div>
      )}
    </>
  );
}
