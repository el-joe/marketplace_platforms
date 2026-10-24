import { cache } from "react";
import type {
  MarketerFilters,
  MarketerProfileData,
  MarketerProfilePageData,
} from "./helpers/types";
import { DEFAULT_FILTERS, LISTINGS_PER_PAGE } from "./helpers/constants";
import { filtersToApiParams } from "./helpers/filters";
import { buildMockListings, isMockEnabled, MOCK_BANNERS } from "./helpers/mock";
import { getLocale } from "next-intl/server";
import resolveCookie from "@/src/helpers/resolveCookie";
import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";

export class MarketerProfileNotFoundError extends Error {}

type RawProfile = MarketerProfileData & Partial<Pick<MarketerProfilePageData, "banners" | "listings" | "facets">>;

const EMPTY_META = { current_page: 1, last_page: 1, per_page: LISTINGS_PER_PAGE, total: 0 };

/** Fills whatever the backend response lacks (mock flag, or legacy sections). */
function normalize(raw: RawProfile, filters: MarketerFilters): MarketerProfilePageData {
  let { banners, listings, facets } = raw;
  const mock = isMockEnabled();

  if (!listings) {
    if (mock) {
      const built = buildMockListings(raw, filters, 1, LISTINGS_PER_PAGE);
      listings = built.listings;
      facets = facets ?? built.facets;
    } else {
      const legacy = raw.own_listings ?? { items: [], meta: EMPTY_META };
      listings = { items: legacy.items, meta: legacy.meta };
    }
  }
  if (!banners) banners = mock ? MOCK_BANNERS : [];

  return { ...raw, banners, listings, facets: facets ?? null };
}

/** Request-scoped dedupe: generateMetadata + page share one fetch. */
const fetchProfile = cache(async (slug: string, qs: string): Promise<RawProfile> => {
  const [country, locale] = await Promise.all([resolveCookie("country"), getLocale()]);
  const res = await fetch(`${apiPublicBaseUrlGlobal}/${country}/marketers/${slug}?${qs}`, {
    cache: "no-store",
    headers: { Accept: "application/json", "Accept-Language": locale.split("-").pop() ?? "en" },
  });
  if (res.status === 404) throw new MarketerProfileNotFoundError();
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const body: { data: RawProfile } = await res.json();
  return body.data;
});

export async function getMarketerProfile(
  slug: string,
  filters: MarketerFilters = DEFAULT_FILTERS,
): Promise<MarketerProfilePageData> {
  const qs = filtersToApiParams(filters, 1, LISTINGS_PER_PAGE).toString();
  return normalize(await fetchProfile(slug, qs), filters);
}
