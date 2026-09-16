import { getTranslations } from "next-intl/server";
import MarketerProfileBanner from "./components/marketer-profile-banner";
import MarketerProfileSidebar from "./components/marketer-profile-sidebar";
import MarketerListingsGrid from "./components/marketer-listings-grid";
import { MarketerProfileData } from "./helpers/types";

interface Props {
  data: MarketerProfileData;
}

export default async function MarketerProfileView({ data }: Props) {
  const { marketer, profile, own_listings, campaign_listings } = data;
  const t = await getTranslations("marketerProfile");

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

          <main className="flex-1 space-y-10">
            <section>
              <h2 className="mb-4 text-xl font-black text-primary">
                {t("selectedProducts")}
                {own_listings.meta.total > 0 && (
                  <span className="ms-2 text-sm font-normal text-gray">
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

            {campaign_listings.meta.total > 0 && (
              <section>
                <h2 className="mb-1 text-xl font-black text-primary">
                  {t("campaignProducts")}
                  <span className="ms-2 text-sm font-normal text-gray">
                    ({t("productsCount", { count: campaign_listings.meta.total })})
                  </span>
                </h2>
                <p className="mb-4 text-xs text-gray">{t("campaignProductsHint")}</p>
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
