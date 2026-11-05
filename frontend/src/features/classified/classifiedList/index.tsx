import TopFilterBar from "./top-filter-bar";
import BreadcrumbAndHeader from "./breadcrumb-and-header";
import CategoryHeader from "./category-header";
import CategoryTags from "./category-tags";
import ClassifiedsPageClient from "./classified-page-client";
import {
  ClassifiedLocationFilters,
  getClassifiedCategoriesService,
  getClassifiedCitiesService,
  getClassifiedsService,
  getClassifiedMapPinsService,
} from "./api/get";
import getLocale from "@/src/helpers/getLocale";
import toCategoryTrail from "./helpers/to-category-trail";
import { CLASSIFIED_PLACEMENT, LEGACY_PLACEMENTS } from "./helpers/constants";
import { IClassifiedBrowseCategory } from "./helpers/types";
import { MapPin } from "./api/get";

type ClassifiedsListProps = {
  /** Slug of the resolved category; omitted on the all-ads landing page. */
  categorySlug?: string;
  /** The category the route already resolved (single source for breadcrumb, header and selection). */
  category?: IClassifiedBrowseCategory | null;
  /** Raw URL search params; only the location filters are used. */
  location?: Record<string, string>;
};

export default async function ClassifiedsList({
  categorySlug,
  category = null,
  location = {},
}: ClassifiedsListProps) {
  const locale = await getLocale();
  const filters: ClassifiedLocationFilters = {
    city_id: location.city_id,
    lat: location.lat,
    lng: location.lng,
    radius_km: location.radius_km,
  };
  const [{ data }, { data: categories }, citiesRes] = await Promise.all([
    getClassifiedsService({ category: categorySlug || "all", filters }),
    getClassifiedCategoriesService(),
    getClassifiedCitiesService(locale).catch(() => null),
  ]);

  // Fetch initial map pins for SSR (best-effort; fail gracefully)
  let initialPins: MapPin[] = [];
  try {
    const pinsRes = await getClassifiedMapPinsService({
      category: category?.id,
      location: filters,
    });
    initialPins = pinsRes?.data ?? [];
  } catch {
    initialPins = [];
  }

  // The landing page lists the sections the admin placed there; a section without
  // placements data (older API) keeps its previous behaviour.
  const classifiedCategories = (categories ?? []).filter(
    (cat) =>
      cat.type === "classified" &&
      (cat.placements ?? LEGACY_PLACEMENTS).includes(
        CLASSIFIED_PLACEMENT.landing,
      ),
  );
  const trail = toCategoryTrail(category);
  const selectedCategory = trail[trail.length - 1] ?? null;

  return (
    <div className="bg-[#f8f9fa] min-h-screen py-4 sm:py-6">
      <div className="container">
        {/* Top 4-dropdown filter bar */}
        <TopFilterBar cities={citiesRes?.data ?? []} />

        {/* Breadcrumb, Page Title & Sort Selector */}
        <BreadcrumbAndHeader
          totalCount={data?.listings?.meta?.total}
          trail={trail}
        />

        {/* Section banner: image, description and sub-categories */}
        {category && <CategoryHeader category={category} />}

        {/* Category Pills Bar */}
        <CategoryTags
          categories={classifiedCategories}
          selectedCtg={trail[0]?.id || null}
        />

        {/* Client component: manages list/map toggle, hover state, pin updates */}
        <ClassifiedsPageClient
          initialPins={initialPins}
          location={filters}
          data={data ?? null}
          classifiedCategories={classifiedCategories}
          categoryId={category?.id}
          selectedCategory={selectedCategory}
        />
      </div>
    </div>
  );
}
