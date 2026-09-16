"use client";
import { useEffect, useRef } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { PackageSearch } from "lucide-react";
import ProductCard from "@/src/components/shared/product-card";
import { Spinner } from "@/src/components/ui/spinner";
import { toProductCard } from "../helpers/to-product-card";
import type { MarketerProfileMarketer, MarketerProfileInfo, MarketerProfileListingItem } from "../helpers/types";
import { getMarketerListings } from "../api";

interface Props {
  slug: string;
  section: "own" | "campaign";
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  initialItems: MarketerProfileListingItem[];
  initialTotal: number;
  initialLastPage: number;
  emptyLabel: string;
  loadMoreLabel: string;
}

export default function MarketerListingsGrid({
  slug, section, marketer, profile,
  initialItems, initialTotal, initialLastPage,
  emptyLabel,
}: Props) {
  const sentinelRef = useRef<HTMLDivElement>(null);

  const query = useInfiniteQuery({
    queryKey: ["marketer-listings", slug, section],
    queryFn: ({ pageParam = 2 }) =>
      getMarketerListings(
        section === "own"
          ? { slug, ownPage: pageParam as number }
          : { slug, campaignPage: pageParam as number }
      ),
    getNextPageParam: (last) => {
      const meta = section === "own"
        ? last.own_listings.meta
        : last.campaign_listings.meta;
      return meta.current_page < meta.last_page ? meta.current_page + 1 : undefined;
    },
    initialPageParam: 2, // page 1 is already SSR
    enabled: initialLastPage > 1,
  });

  // Intersection observer — load more when sentinel enters viewport
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) return;
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting && query.hasNextPage && !query.isFetchingNextPage) query.fetchNextPage(); },
      { threshold: 0.1 }
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [query]);

  // Flatten fetched pages
  const fetchedItems = query.data?.pages.flatMap((page) =>
    (section === "own" ? page.own_listings : page.campaign_listings).items
  ) ?? [];

  const allItems = [...initialItems, ...fetchedItems];

  if (allItems.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 py-16 text-center">
        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-2">
          <PackageSearch className="h-6 w-6 text-gray" />
        </div>
        <p className="text-sm font-semibold text-gray">{emptyLabel}</p>
      </div>
    );
  }

  return (
    <>
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">
        {allItems.map((item) => (
          <div key={item.listing_id} className="[&>div]:w-full">
            <ProductCard productData={toProductCard(item, marketer, profile)} />
          </div>
        ))}
      </div>

      {/* Sentinel for infinite scroll */}
      <div ref={sentinelRef} className="h-4 mt-4" />
      {query.isFetchingNextPage && (
        <div className="flex justify-center py-6">
          <Spinner />
        </div>
      )}
    </>
  );
}
