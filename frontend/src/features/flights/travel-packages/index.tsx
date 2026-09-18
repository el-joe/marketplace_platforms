import { Suspense } from "react";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import TravelPackageCard from "./components/package-card";
import CategoryTabs from "./components/category-tabs";
import TravelFilters from "./components/travel-filters";
import TravelHero from "./components/travel-hero";
import PackagesPagination from "./components/packages-pagination";
import { PACKAGES_PER_PAGE, DESTINATION_OPTIONS_PER_PAGE } from "./helpers/constants";
import { buildDestinationOptions } from "./helpers/build-destination-options";
import { getTravelPackages } from "../api/travel-packages.actions";

type Props = {
  searchParams: {
    page?: string;
    category?: string;
    country_id?: string;
    city_id?: string;
    date_from?: string;
    date_to?: string;
  };
};

export default async function TravelPackagesListing({ searchParams }: Props) {
  const t = await getTranslations("flights");

  const page = Math.max(1, Number(searchParams.page) || 1);

  const activeCategorySlug = searchParams.category;
  const countryId = searchParams.country_id;
  const cityId = searchParams.city_id;
  const dateFrom = searchParams.date_from;
  const dateTo = searchParams.date_to;

  const [result, optionsResult] = await Promise.all([
    getTravelPackages({
      categoryId: activeCategorySlug,
      page,
      perPage: PACKAGES_PER_PAGE,
      countryId,
      cityId,
      dateFrom,
      dateTo,
    }),
    // Unfiltered (category-scoped only) fetch used solely to derive the set
    // of available country/city filter options — there is no dedicated
    // destinations endpoint on the customer browse API.
    getTravelPackages({
      categoryId: activeCategorySlug,
      perPage: DESTINATION_OPTIONS_PER_PAGE,
    }),
  ]);

  if (!result) {
    notFound();
  }

  const { countries, cities } = buildDestinationOptions(
    optionsResult?.listings.items ?? [],
  );

  const {
    available_categories,
    listings: { items, meta },
  } = result;

  return (
    <section className="container py-6 max-w-[1200px]">
      <div className="mb-6">
        <TravelHero featuredPackage={items[0]} />
      </div>

      {available_categories.length > 0 && (
        <div className="mb-6">
          <Suspense>
            <CategoryTabs
              categories={available_categories}
              activeCategorySlug={activeCategorySlug ?? null}
            />
          </Suspense>
        </div>
      )}

      <div className="mb-6">
        <Suspense>
          <TravelFilters countries={countries} cities={cities} />
        </Suspense>
      </div>

      <div className="flex items-center justify-between mb-4">
        <p className="text-sm text-gray">
          {t("packagesAvailable", { count: meta.total })}
        </p>
      </div>

      {items.length === 0 ? (
        <div className="mt-16 flex flex-col items-center gap-3 text-center">
          <span className="text-6xl">✈️</span>
          <p className="font-semibold text-lg text-primary">
            {t("noPackagesFound")}
          </p>
          <p className="text-sm text-gray">{t("tryAdjustingFilter")}</p>
        </div>
      ) : (
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {items.map((pkg) => (
            <TravelPackageCard key={pkg.package_id} pkg={pkg} />
          ))}
        </div>
      )}

      <div className="mt-8">
        <PackagesPagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
        />
      </div>
    </section>
  );
}
