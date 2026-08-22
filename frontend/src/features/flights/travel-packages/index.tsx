import { PlaneIcon } from "lucide-react";
import { getTranslations } from "next-intl/server";
import TravelPackageCard from "./components/package-card";
import HeroBanner from "./components/hero-banner";
import PackagesPagination from "./components/packages-pagination";
import { PACKAGES_PER_PAGE, HERO_PACKAGES_COUNT } from "./helpers/constants";
import { getTravelPackages } from "../api/travel-packages.actions";

type Props = {
  searchParams: {
    page?: string;
  };
};

export default async function TravelPackagesListing({ searchParams }: Props) {
  const t = await getTranslations("flights");

  const page = Math.max(1, Number(searchParams.page) || 1);

  const {
    listings: { items, meta },
  } = await getTravelPackages({ page, perPage: PACKAGES_PER_PAGE });

  return (
    <>
      <div className="mb-8">
        <HeroBanner packages={items.slice(0, HERO_PACKAGES_COUNT)} />
      </div>

      <section className="container py-8 max-w-[1200px]">
        <div className="flex items-center gap-3 mb-6">
          <div className="flex items-center justify-center size-10 rounded-full bg-blue-3/10">
            <PlaneIcon className="size-5 text-blue-3" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-primary">
              {t("travelPackages")}
            </h1>
            <p className="text-sm text-gray">
              {t("packagesAvailable", { count: meta.total })}
            </p>
          </div>
        </div>

        {items.length === 0 ? (
          <div className="mt-16 flex flex-col items-center gap-3 text-center">
            <PlaneIcon className="size-12 text-gray opacity-30" />
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
    </>
  );
}
