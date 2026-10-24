import type { MarketerFilters, MarketerListings, MarketerProfileData } from "./helpers/types";
import { LISTINGS_PER_PAGE } from "./helpers/constants";
import { filtersToApiParams } from "./helpers/filters";
import { buildMockListings, isMockEnabled } from "./helpers/mock";
import resolveCookie from "@/src/helpers/resolveCookie";
import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";

// Client-safe (no react `cache`): used by the load-more hook in the browser.
const EMPTY_META = { current_page: 1, last_page: 1, per_page: LISTINGS_PER_PAGE, total: 0 };

/** Next page of the filtered grid (called from the client for load-more). */
export async function getMarketerListingsPage(
  slug: string,
  filters: MarketerFilters,
  page: number,
): Promise<MarketerListings> {
  const country = await resolveCookie("country");
  const qs = filtersToApiParams(filters, page, LISTINGS_PER_PAGE).toString();
  const res = await fetch(`${apiPublicBaseUrlGlobal}/${country}/marketers/${slug}?${qs}`, {
    cache: "no-store",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const body: { data: (MarketerProfileData & { listings?: MarketerListings }) } = await res.json();
  if (body.data.listings) return body.data.listings;
  if (isMockEnabled()) return buildMockListings(body.data, filters, page, LISTINGS_PER_PAGE).listings;
  return { items: [], meta: { ...EMPTY_META, current_page: page } };
}
