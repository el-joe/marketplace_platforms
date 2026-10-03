"use client";
import { useState } from "react";
import Link from "next/link";
import { useLocale } from "next-intl";
import MarketerListingsGrid from "./marketer-listings-grid";
import type {
  MarketerClassifiedListing,
  MarketerProfileInfo,
  MarketerProfileListingItem,
  MarketerProfileMarketer,
  ListingsMeta,
} from "../helpers/types";

interface ListingSection {
  items: MarketerProfileListingItem[];
  meta: ListingsMeta;
}

interface Tab {
  key: string;
  label: string;
  count: number;
}

interface Props {
  slug: string;
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  ownListings: ListingSection;
  vendorCampaignListings: ListingSection;
  marketerCampaignListings: ListingSection;
  classifiedListings: MarketerClassifiedListing[];
  // i18n strings passed from the RSC parent
  t: {
    tabOwn: string;
    tabVendorCampaigns: string;
    tabMarketerCampaigns: string;
    tabClassifieds: string;
    productsCount: (count: number) => string;
    emptyProducts: string;
    loadMore: string;
    priceNegotiable: string;
  };
}

export default function MarketerProfileTabs({
  slug,
  marketer,
  profile,
  ownListings,
  vendorCampaignListings,
  marketerCampaignListings,
  classifiedListings,
  t,
}: Props) {
  const locale = useLocale();
  const isAr = locale === "ar";

  const tabs: Tab[] = [
    { key: "own", label: t.tabOwn, count: ownListings.meta.total },
    { key: "vendor_campaign", label: t.tabVendorCampaigns, count: vendorCampaignListings.meta.total },
    { key: "marketer_campaign", label: t.tabMarketerCampaigns, count: marketerCampaignListings.meta.total },
    { key: "classifieds", label: t.tabClassifieds, count: classifiedListings.length },
  ].filter((tab) => tab.count > 0 || tab.key === "own");

  const [activeTab, setActiveTab] = useState<string>(tabs[0]?.key ?? "own");

  return (
    <div className="flex-1 min-w-0">
      {/* Tab bar */}
      <div className="flex flex-wrap gap-2 border-b border-border-color pb-0 mb-6 overflow-x-auto scrollbar-none">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={[
                "relative shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold",
                "border-b-2 transition-colors whitespace-nowrap",
                isActive
                  ? "border-primary text-primary"
                  : "border-transparent text-gray hover:text-primary hover:border-primary/40",
              ].join(" ")}
            >
              {tab.label}
              {tab.count > 0 && (
                <span
                  className={[
                    "inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-xs leading-none",
                    isActive ? "bg-primary/10 text-primary" : "bg-gray-2 text-gray",
                  ].join(" ")}
                >
                  {tab.count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Tab panels */}
      {activeTab === "own" && (
        <MarketerListingsGrid
          slug={slug}
          section="own"
          marketer={marketer}
          profile={profile}
          initialItems={ownListings.items}
          initialTotal={ownListings.meta.total}
          initialLastPage={ownListings.meta.last_page}
          emptyLabel={t.emptyProducts}
          loadMoreLabel={t.loadMore}
        />
      )}

      {activeTab === "vendor_campaign" && (
        <MarketerListingsGrid
          slug={slug}
          section="vendor_campaign"
          marketer={marketer}
          profile={profile}
          initialItems={vendorCampaignListings.items}
          initialTotal={vendorCampaignListings.meta.total}
          initialLastPage={vendorCampaignListings.meta.last_page}
          emptyLabel={t.emptyProducts}
          loadMoreLabel={t.loadMore}
        />
      )}

      {activeTab === "marketer_campaign" && (
        <MarketerListingsGrid
          slug={slug}
          section="marketer_campaign"
          marketer={marketer}
          profile={profile}
          initialItems={marketerCampaignListings.items}
          initialTotal={marketerCampaignListings.meta.total}
          initialLastPage={marketerCampaignListings.meta.last_page}
          emptyLabel={t.emptyProducts}
          loadMoreLabel={t.loadMore}
        />
      )}

      {activeTab === "classifieds" && (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
          {classifiedListings.map((l) => (
            <Link
              key={l.id}
              href={`/classified/find/${l.slug}`}
              className="overflow-hidden rounded-lg border border-border-color bg-white hover:shadow-md transition-shadow"
            >
              {l.first_image && (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={l.first_image}
                  alt={isAr ? l.title_ar : (l.title_en ?? l.title_ar)}
                  className="aspect-4/3 w-full object-cover"
                />
              )}
              <div className="p-3">
                <p className="line-clamp-2 text-sm font-bold">
                  {isAr ? l.title_ar : (l.title_en ?? l.title_ar)}
                </p>
                <p className="mt-1 text-sm text-primary">
                  {l.price.toLocaleString()} {l.currency}
                  {l.price_negotiable && (
                    <span className="ms-2 text-xs text-gray">{t.priceNegotiable}</span>
                  )}
                </p>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
