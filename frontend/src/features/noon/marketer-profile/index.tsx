import React from "react";
import { getMarketerProfile } from "./api";
import Image from "next/image";
import HeroBanner from "./hero-banner";
import ProductCard from "@/src/components/shared/product-card";
import Filters from "./filters";
import Pagination from "@/src/components/shared/pagination";
import SmallScreenFiltersSheet from "./small-screen-filters";
import ProfileActions from "./profile-actions";
import ProductsList from "./products-list";

export default async function MarketerProfile({
  slug,
  params,
}: {
  slug: string;
  params: Record<string, string>;
}) {
  const marketerProfile = await getMarketerProfile(
    slug,
    new URLSearchParams(params),
  );
  console.log("new URLSearchParams(params),", new URLSearchParams(params));
  return (
    <div>
      <div className="container flex justify-end py-2">
        <ProfileActions
          marketerName={marketerProfile.data.marketer.name}
          profileUrl={marketerProfile.data.profile.profile_url}
          qrCodeUrl={marketerProfile.data.profile.qr_code_url}
        />
      </div>
      <Image
        src={
          marketerProfile.data.profile.banner_url ??
          "/images/no-image-available-icon.jpg"
          // "https://v2cdn.boutiqaat.com/media/boutiqaat_celebrity/W/e/Web-Ar_2025_03_27_16_37_29_700.jpg"
        }
        alt=""
        width={4400}
        height={1860}
        className="mx-auto mb-4 max-h-[70vh]"
      />
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
              title="listing items"
              meta={marketerProfile?.data?.listings?.meta}
            />
            {/* own listings */}
            <ProductsList
              list={marketerProfile.data.own_listings.items}
              listType="own-listing"
              title="own listing items"
              meta={marketerProfile?.data?.own_listings?.meta}
            />
            {/* campaign listings */}
            <ProductsList
              list={marketerProfile.data.campaign_listings.items}
              listType="campaign-listing"
              title="campaign listing items"
              meta={marketerProfile?.data?.campaign_listings?.meta}
            />
            {/* vendor campaign listings */}
            <ProductsList
              list={marketerProfile.data.vendor_campaign_listings.items}
              listType="vendor-campaign-listing"
              title="vendor campaign listing items"
              meta={marketerProfile?.data?.vendor_campaign_listings?.meta}
            />
            {/* marketer campaign listings */}
            <ProductsList
              list={marketerProfile.data.marketer_campaign_listings.items}
              listType="marketer-campaign-listing"
              title="marketer campaign listing items"
              meta={marketerProfile?.data?.marketer_campaign_listings?.meta}
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
