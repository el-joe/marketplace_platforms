import { getTranslations } from "next-intl/server";
import MarketerProfileBanner from "./marketer-profile-banner";
import MarketerProfileSidebar from "./marketer-profile-sidebar";
import MarketerListingsGrid from "./marketer-listings-grid";
import { MarketerProfileData } from "./helpers/types";

interface Props {
  data: MarketerProfileData;
}

export default async function MarketerProfileView({ data }: Props) {
  const { marketer, profile, own_listings, campaign_listings } = data;
  const t = await getTranslations("marketerProfile");

  return (
    <div className="bg-white min-h-screen">
      <MarketerProfileBanner
        bannerUrl={profile.banner_url}
        avatarUrl={profile.avatar_url}
        marketerName={marketer.name}
        marketerType={marketer.marketer_type}
        profileUrl={profile.profile_url}
        qrCodeUrl={profile.qr_code_url}
        labels={{
          influencerBadge: t("influencerBadge"),
          affiliateBadge: t("affiliateBadge"),
          copied: t("copied"),
          share: t("share"),
          qrTitle: t("qrTitle"),
          qrScanHint: t("qrScanHint", { name: marketer.name }),
          download: t("download"),
          close: t("close"),
        }}
      />

      <div className="container mx-auto px-4 py-8">
        <div className="flex flex-col lg:flex-row gap-8 items-start">
          <MarketerProfileSidebar marketer={marketer} profile={profile} />
          <div className="hidden lg:block w-px bg-gray-200 self-stretch" />

          <main className="flex-1 space-y-10">
            {/* Section A — Own products */}
            <section>
              <h2 className="text-xl font-bold text-gray-900 mb-4">
                {t("selectedProducts")}
                {own_listings.meta.total > 0 && (
                  <span className="ms-2 text-sm font-normal text-gray-400">
                    ({t("productsCount", { count: own_listings.meta.total })})
                  </span>
                )}
              </h2>
              <MarketerListingsGrid
                slug={profile.slug}
                section="own"
                marketer={marketer}
                profile={profile}
                initialItems={own_listings.items}
                initialTotal={own_listings.meta.total}
                initialLastPage={own_listings.meta.last_page}
                emptyLabel={t("emptyProducts")}
                loadMoreLabel={t("loadMore")}
              />
            </section>

            {/* Section B — Campaign products (only if any) */}
            {campaign_listings.meta.total > 0 && (
              <section>
                <h2 className="text-xl font-bold text-gray-900 mb-1">
                  {t("campaignProducts")}
                  <span className="ms-2 text-sm font-normal text-gray-400">
                    ({t("productsCount", { count: campaign_listings.meta.total })})
                  </span>
                </h2>
                <p className="text-xs text-gray-400 mb-4">{t("campaignProductsHint")}</p>
                <MarketerListingsGrid
                  slug={profile.slug}
                  section="campaign"
                  marketer={marketer}
                  profile={profile}
                  initialItems={campaign_listings.items}
                  initialTotal={campaign_listings.meta.total}
                  initialLastPage={campaign_listings.meta.last_page}
                  emptyLabel={t("emptyProducts")}
                  loadMoreLabel={t("loadMore")}
                />
              </section>
            )}
          </main>
        </div>
      </div>
    </div>
  );
}
