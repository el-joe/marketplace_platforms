import React from "react";
import { getMarketerProfile } from "./api";
import HeroBanner from "./hero-banner";
import Filters from "./filters";
import ProfileHeader from "./profile-header";
import ListingTabs from "./listing-tabs";
import ListingToolbar from "./listing-toolbar";
import ListingsGrid from "./listings-grid";
import Pagination from "@/src/components/shared/pagination";
import { ProfileKind } from "./helpers/types";

const KINDS: ProfileKind[] = ["products", "classified", "travel"];

const isKind = (value?: string): value is ProfileKind =>
  KINDS.includes(value as ProfileKind);

export default async function MarketerProfile({
  slug,
  params,
}: {
  slug: string;
  params: Record<string, string>;
}) {
  const requestedKind = isKind(params.kind) ? params.kind : undefined;
  const fetchProfile = (kind: ProfileKind) =>
    getMarketerProfile(slug, new URLSearchParams({ ...params, kind }));

  let kind: ProfileKind = requestedKind ?? "products";
  let marketerProfile = await fetchProfile(kind);

  // No explicit tab and nothing under Products: open the first tab that has content.
  if (!requestedKind) {
    const counts = Object.fromEntries(
      marketerProfile.data.facets.kinds.map((k) => [k.key, k.count]),
    );
    const firstWithContent = KINDS.find((k) => counts[k]);
    if (!counts.products && firstWithContent) {
      kind = firstWithContent;
      marketerProfile = await fetchProfile(kind);
    }
  }

  const { listings, facets, banners } = marketerProfile.data;

  return (
    <div>
      <ProfileHeader data={marketerProfile.data} />
      <div className="container">
        <div className="relative flex items-start gap-3">
          <div className="hidden w-75 lg:block lg:min-w-50 xl:min-w-75">
            <Filters facets={facets} kind={kind} />
          </div>
          <div className="flex-1 overflow-hidden">
            <HeroBanner banners={banners} />
            <ListingTabs active={kind} kinds={facets.kinds} />
            <ListingToolbar
              facets={facets}
              kind={kind}
              total={listings.meta.total}
            />
            <ListingsGrid items={listings.items} kind={kind} />
            {listings.meta.last_page > 1 && (
              <Pagination totalPages={listings.meta.last_page} />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
