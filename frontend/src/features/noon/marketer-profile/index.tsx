import React from "react";
import { getMarketerProfile } from "./api";
import Image from "next/image";
import HeroBanner from "./hero-banner";
import Filters from "./filters";
import SmallScreenFiltersSheet from "./small-screen-filters";
import ProfileHeader from "./profile-header";
import ProductsList from "./products-list";
import { getTranslations } from "next-intl/server";

export default async function MarketerProfile({
  slug,
  params,
}: {
  slug: string;
  params: Record<string, string>;
}) {
  const t = await getTranslations("marketerProfile");
  const marketerProfile = await getMarketerProfile(
    slug,
    new URLSearchParams(params),
  );
  return (
    <div>
      <ProfileHeader data={marketerProfile.data} />
      <div className="container">
        <div className="flex items-start gap-3 relative">
          <div className="lg:min-w-50 xl:min-w-75 w-75 hidden lg:block">
            <Filters facets={marketerProfile.data.facets} />
          </div>
          <div className="flex-1 overflow-hidden">
            <HeroBanner banners={marketerProfile.data.banners} />
            <SmallScreenFiltersSheet facets={marketerProfile.data.facets} />
            {/* listings */}
            {}
            <ProductsList
              list={marketerProfile.data.listings.items}
              listType="listing"
              title={t("listings")}
              // meta={marketerProfile?.data?.listings?.meta}
            />
            {/* own listings */}
            <ProductsList
              list={marketerProfile.data.own_listings.items}
              listType="own-listing"
              title={t("ownListings")}
              // meta={marketerProfile?.data?.own_listings?.meta}
            />
            {/* campaign listings */}
            <ProductsList
              list={marketerProfile.data.campaign_listings.items}
              listType="campaign-listing"
              title={t("campaignListings")}
              // meta={marketerProfile?.data?.campaign_listings?.meta}
            />
            {/* vendor campaign listings */}
            <ProductsList
              list={marketerProfile.data.vendor_campaign_listings.items}
              listType="vendor-campaign-listing"
              title={t("vendorCampaignListings")}
              // meta={marketerProfile?.data?.vendor_campaign_listings?.meta}
            />
            {/* marketer campaign listings */}
            <ProductsList
              list={marketerProfile.data.marketer_campaign_listings.items}
              listType="marketer-campaign-listing"
              title={t("marketerCampaignListings")}
              // meta={marketerProfile?.data?.marketer_campaign_listings?.meta}
            />
            {!marketerProfile.data.listings.items.length && (
              <Image
                src="/images/empty-result.webp"
                alt=""
                width={1280}
                height={1280}
                className="w-md mx-auto"
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
