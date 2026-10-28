import TopFilterBar from "./top-filter-bar";
import BreadcrumbAndHeader from "./breadcrumb-and-header";
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
import getSelectedCategoryTree from "./helpers/get-selected-categories-tree";
import { getTranslations } from "next-intl/server";
import { MapPin } from "./api/get";

type ClassifiedsListProps = {
  categoryId?: string;
  /** Raw URL search params; only the location filters are used. */
  location?: Record<string, string>;
};

export default async function ClassifiedsList({
  categoryId,
  location = {},
}: ClassifiedsListProps) {
  const t = await getTranslations("classified");
  const locale = await getLocale();
  const filters: ClassifiedLocationFilters = {
    city_id: location.city_id,
    lat: location.lat,
    lng: location.lng,
    radius_km: location.radius_km,
  };
  const [{ data }, { data: categories }, citiesRes] = await Promise.all([
    getClassifiedsService({ category: categoryId || "all", filters }),
    getClassifiedCategoriesService(),
    getClassifiedCitiesService(locale).catch(() => null),
  ]);

  // Fetch initial map pins for SSR (best-effort; fail gracefully)
  let initialPins: MapPin[] = [];
  try {
    const pinsRes = await getClassifiedMapPinsService({
      category: categoryId,
      location: filters,
    });
    initialPins = pinsRes?.data ?? [];
  } catch {
    initialPins = [];
  }

  const classifiedCategories = categories?.filter(
    (cat) => cat.type === "classified",
  );
  const SelectedCategoryTree = getSelectedCategoryTree(
    categoryId || null,
    classifiedCategories || [],
  );
  const selectedCategory =
    SelectedCategoryTree?.[SelectedCategoryTree.length - 1];

  return (
    <div className="bg-[#f8f9fa] min-h-screen py-4 sm:py-6">
      <div className="container">
        {/* Top 4-dropdown filter bar */}
        <TopFilterBar cities={citiesRes?.data ?? []} />

        {/* Breadcrumb, Page Title & Sort Selector */}
        <BreadcrumbAndHeader
          totalCount={data?.listings?.meta?.total}
          selectedCtg={categoryId || null}
          categories={classifiedCategories || []}
        />

        {/* Category Pills Bar */}
        <CategoryTags
          categories={classifiedCategories}
          selectedCtg={SelectedCategoryTree[0]?.id || null}
        />

        {/* Client component: manages list/map toggle, hover state, pin updates */}
        <ClassifiedsPageClient
          initialPins={initialPins}
          location={filters}
          data={data ?? null}
          classifiedCategories={classifiedCategories || []}
          categoryId={categoryId}
          selectedCategory={selectedCategory ?? null}
        />
      </div>
    </div>
  );
}
