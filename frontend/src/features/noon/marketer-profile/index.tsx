import { getLocale, getTranslations } from "next-intl/server";
import MarketerProfileBanner from "./components/marketer-profile-banner";
import MarketerProfileSidebar from "./components/marketer-profile-sidebar";
import MarketerProfileTabs from "./components/marketer-profile-tabs";
import { MarketerProfileData } from "./helpers/types";

interface Props {
  data: MarketerProfileData;
}

export default async function MarketerProfileView({ data }: Props) {
  const { marketer, profile, own_listings } = data;
  // Fall back to legacy campaign_listings for backward compat during rollout
  const emptySection = { items: [], meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 } };
  const vendor_campaign_listings = data.vendor_campaign_listings ?? data.campaign_listings ?? emptySection;
  const marketer_campaign_listings = data.marketer_campaign_listings ?? emptySection;
  const t = await getTranslations("marketerProfile");
  const locale = await getLocale();
  const isAr = locale === "ar";
  const contracts = data.exclusive_contracts ?? [];
  const classified = data.classified_listings ?? [];

  return (
    <div className="min-h-screen bg-white">
      <MarketerProfileBanner
        bannerUrl={profile.banner_url}
        avatarUrl={profile.avatar_url}
        marketerName={marketer.name}
        marketerType={marketer.marketer_type}
        profileUrl={profile.profile_url}
        qrCodeUrl={profile.qr_code_url}
      />

      <div className="container mx-auto px-4 py-8">
        <div className="flex flex-col items-start gap-8 lg:flex-row">
          <MarketerProfileSidebar marketer={marketer} profile={profile} />
          <div className="hidden self-stretch border-l border-border-color lg:block" />

          <main className="flex-1 min-w-0 space-y-6">
            {contracts.length > 0 && (
              <div className="flex flex-wrap items-center gap-2 text-xs font-semibold text-amber-700">
                <span>{t("activeExclusiveContracts")}:</span>
                {contracts.map((c, i) => (
                  <span key={i} className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1">
                    {(c.scope === "listing"
                      ? isAr ? c.listing_title : c.listing_title_en ?? c.listing_title
                      : isAr ? c.category_name : c.category_name_en ?? c.category_name) ?? ""}
                  </span>
                ))}
              </div>
            )}

            <MarketerProfileTabs
              slug={profile.slug}
              marketer={marketer}
              profile={profile}
              ownListings={own_listings}
              vendorCampaignListings={vendor_campaign_listings}
              marketerCampaignListings={marketer_campaign_listings}
              classifiedListings={classified}
              t={{
                tabOwn: t("tabOwn"),
                tabVendorCampaigns: t("tabVendorCampaigns"),
                tabMarketerCampaigns: t("tabMarketerCampaigns"),
                tabClassifieds: t("tabClassifieds"),
                emptyProducts: t("emptyProducts"),
                loadMore: t("loadMore"),
                priceNegotiable: t("priceNegotiable"),
              }}
            />
          </main>
        </div>
      </div>
    </div>
  );
}
