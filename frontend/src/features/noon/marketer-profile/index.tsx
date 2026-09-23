import Link from "next/link";
import { getLocale, getTranslations } from "next-intl/server";
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

          <main className="flex-1 space-y-10">
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

            {classified.length > 0 && (
              <section>
                <h2 className="mb-4 text-xl font-black text-primary">{t("classifiedListings")}</h2>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                  {classified.map((l) => (
                    <Link
                      key={l.id}
                      href={`/classified/find/${l.slug}`}
                      className="overflow-hidden rounded-lg border border-border-color bg-white"
                    >
                      {l.first_image && (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={l.first_image} alt={isAr ? l.title_ar : l.title_en ?? l.title_ar} className="aspect-4/3 w-full object-cover" />
                      )}
                      <div className="p-3">
                        <p className="line-clamp-2 text-sm font-bold">{isAr ? l.title_ar : l.title_en ?? l.title_ar}</p>
                        <p className="mt-1 text-sm text-primary">
                          {l.price.toLocaleString()} {l.currency}
                          {l.price_negotiable && (
                            <span className="ms-2 text-xs text-gray">{t("priceNegotiable")}</span>
                          )}
                        </p>
                      </div>
                    </Link>
                  ))}
                </div>
              </section>
            )}
          </main>
        </div>
      </div>
    </div>
  );
}
