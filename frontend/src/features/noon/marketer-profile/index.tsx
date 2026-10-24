import { getLocale, getTranslations } from "next-intl/server";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import MarketerBannersCarousel from "./components/marketer-banners-carousel";
import MarketerCatalog from "./components/marketer-catalog";
import MarketerProfileAbout from "./components/marketer-profile-about";
import MarketerProfileHeader from "./components/marketer-profile-header";
import { KIND_OPTIONS, SORT_OPTIONS, SOURCE_OPTIONS } from "./helpers/constants";
import type { MarketerFilters, MarketerProfilePageData } from "./helpers/types";

interface Props {
  data: MarketerProfilePageData;
  filters: MarketerFilters;
}

export default async function MarketerProfileView({ data, filters }: Props) {
  const { marketer, profile, banners, listings, facets } = data;
  const t = await getTranslations("marketerProfile");
  const isAr = (await getLocale()).endsWith("ar");
  const contracts = data.exclusive_contracts ?? [];

  const sourceKeys: Record<string, string> = { all: "sourceAll", own: "sourceOwn", for_sellers: "sourceForSellers" };
  const kindKeys: Record<string, string> = { all: "kindAll", products: "kindProducts", classified: "kindClassified" };
  const sortKeys: Record<string, string> = { newest: "sortNewest", price_asc: "sortPriceAsc", price_desc: "sortPriceDesc" };

  const labels = {
    panel: {
      source: t("filterSource"),
      sourceOptions: Object.fromEntries(SOURCE_OPTIONS.map((k) => [k, t(sourceKeys[k])])),
      kind: t("filterKind"),
      kindOptions: Object.fromEntries(KIND_OPTIONS.map((k) => [k, t(kindKeys[k])])),
      category: t("filterCategory"),
      brand: t("filterBrand"),
      price: t("filterPrice"),
      priceMin: t("priceMin"),
      priceMax: t("priceMax"),
      applyPrice: t("applyPrice"),
      discounts: t("filterDiscounts"),
      discountedOnly: t("discountedOnly"),
      showMore: t("showMore"),
      showLess: t("showLess"),
      searchFacet: t("searchFacet"),
    },
    toolbar: {
      searchPlaceholder: t("searchPlaceholder"),
      sortBy: t("sortBy"),
      sortOptions: Object.fromEntries(SORT_OPTIONS.map((k) => [k, t(sortKeys[k])])),
      openFilter: t("openFilter"),
    },
    resultsCount: t("resultsCount", { count: listings.meta.total }),
    filtersTitle: t("filtersTitle"),
    closeFilter: t("closeFilter"),
    clearAll: t("clearAll"),
    removeFilter: t("removeFilter"),
    grid: {
      empty: t("noResults"),
      emptyHint: t("noResultsHint"),
      loadMore: t("loadMore"),
      retry: t("retry"),
      exclusive: t("exclusiveBadge"),
      negotiable: t("priceNegotiable"),
      classified: t("classifiedBadge"),
    },
  };

  return (
    <div className="min-h-screen bg-white">
      <div className="container mx-auto space-y-5 px-4 py-6">
        <Breadcrumb
          list={[
            { label: t("breadcrumbHome"), href: "/" },
            { label: t("breadcrumbMarketers"), href: "/marketers" },
            { label: marketer.name, href: "" },
          ]}
        />

        <MarketerProfileHeader marketer={marketer} profile={profile} />

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

        <MarketerProfileAbout marketer={marketer} profile={profile} />

        <MarketerCatalog slug={profile.slug} filters={filters} listings={listings} facets={facets} labels={labels}>
          {banners.length > 0 ? (
            <MarketerBannersCarousel banners={banners} prevLabel={t("previous")} nextLabel={t("next")} />
          ) : profile.banner_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={profile.banner_url} alt={marketer.name} className="aspect-[1000/280] w-full rounded-xl object-cover" />
          ) : null}
        </MarketerCatalog>
      </div>
    </div>
  );
}
