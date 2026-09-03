import { getLocale } from "next-intl/server";
import { GridIcon } from "lucide-react";

import Pagination from "@/src/components/shared/pagination";

import { getClassifiedBrowse } from "./api/browse.actions";
import ClassifiedGrid from "./components/classified-grid";
import type { ClassifiedBrowseFilters } from "./helpers/types";

type Props = {
  categoryId?: string;
  searchParams: Record<string, string>;
};

export default async function ClassifiedBrowse({
  categoryId = "all",
  searchParams,
}: Props) {
  const locale = await getLocale();

  const filters: ClassifiedBrowseFilters = {
    page: Number(searchParams.page) || 1,
    per_page: 20,
    ...(searchParams.listing_purpose && {
      listing_purpose: searchParams.listing_purpose as "sale" | "rent",
    }),
    ...(searchParams.seller_type && {
      seller_type: searchParams.seller_type as "vendor" | "customer",
    }),
    ...(searchParams.min_price && {
      min_price: Number(searchParams.min_price),
    }),
    ...(searchParams.max_price && {
      max_price: Number(searchParams.max_price),
    }),
  };

  let data = null;
  try {
    data = await getClassifiedBrowse(categoryId, filters);
  } catch {
    data = null;
  }

  const listings = data?.listings?.items ?? [];
  const meta = data?.listings?.meta;
  const category = data?.category;
  const categoryName = category?.name?.[locale as "en" | "ar"] ?? "Classifieds";

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-primary">{categoryName}</h1>
          {meta && (
            <p className="text-sm text-gray mt-0.5">
              {meta.total.toLocaleString()} listings
            </p>
          )}
        </div>
        <GridIcon className="size-5 text-gray" />
      </div>

      {listings.length === 0 ? (
        <div className="flex flex-col items-center gap-3 py-24 text-center">
          <span className="text-6xl">🔍</span>
          <p className="font-semibold text-lg">No listings found</p>
          <p className="text-sm text-gray">Try adjusting your filters</p>
        </div>
      ) : (
        <ClassifiedGrid listings={listings} />
      )}

      {meta && meta.last_page > 1 && (
        <div className="mt-6">
          <Pagination totalPages={meta.last_page} />
        </div>
      )}
    </div>
  );
}
