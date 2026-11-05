import { fetchInstance } from "@/src/lib/utils";
import {
  IClassifiedCategoriesList,
  IClassifiedSection,
  IClassifiedsList,
} from "../helpers/types";

/** Location filters shared by the list, the map pins and the top filter bar (all optional, URL-driven). */
export interface ClassifiedLocationFilters {
  city_id?: string;
  lat?: string;
  lng?: string;
  radius_km?: string;
}

export interface ClassifiedCity {
  id: string;
  name: string;
  count: number;
}

const locationQuery = (filters?: ClassifiedLocationFilters) => {
  const params = new URLSearchParams();
  (["city_id", "lat", "lng", "radius_km"] as const).forEach((key) => {
    const value = filters?.[key];
    if (value) params.set(key, value);
  });
  return params;
};

export const getClassifiedsService = ({
  category = "all",
  filters,
}: {
  category?: string;
  filters?: ClassifiedLocationFilters;
}) => {
  const qs = locationQuery(filters).toString();
  return fetchInstance<{ data: IClassifiedsList }>(
    `/browse/classified/${category}${qs ? `?${qs}` : ""}`,
    { method: "GET" },
  );
};

/** Light call that only resolves a category (slug or legacy id) for metadata, redirects and 404s. */
export const getClassifiedCategoryService = (idOrSlug: string) =>
  fetchInstance<{ data: IClassifiedsList }>(
    `/browse/classified/${encodeURIComponent(idOrSlug)}?per_page=1`,
    { method: "GET" },
  );

/** Sections the admin placed on a storefront surface, with their newest ads (no hard-coded sections). */
export const getClassifiedSectionsService = ({
  placement,
  sections,
  perSection,
}: {
  placement: "home" | "landing" | "nav";
  sections?: number;
  perSection?: number;
}) => {
  const params = new URLSearchParams({ placement });
  if (sections) params.set("sections", String(sections));
  if (perSection) params.set("per_section", String(perSection));
  return fetchInstance<{ data: IClassifiedSection[] }>(
    `/classified/sections?${params}`,
    { method: "GET" },
  );
};

export const getClassifiedCitiesService = (locale: string) =>
  fetchInstance<{ data: ClassifiedCity[] }>(
    `/classified/cities?${new URLSearchParams({ locale })}`,
  );

export const getClassifiedCategoriesService = () =>
  fetchInstance<{ data: IClassifiedCategoriesList[] }>(`/categories`, {
    method: "GET",
  });

export interface MapPin {
  id: string;
  number: string;
  slug: string;
  title: string;
  price: number;
  currency: string;
  purpose: string;
  lat: number;
  lng: number;
  thumbnail: string | null;
}

export const getClassifiedMapPinsService = (params?: {
  bounds?: { south: number; north: number; east: number; west: number };
  category?: string;
  purpose?: string;
  city_id?: string;
  location?: ClassifiedLocationFilters;
}) => {
  const searchParams = locationQuery(params?.location);
  if (params?.bounds) {
    searchParams.set("bounds[south]", String(params.bounds.south));
    searchParams.set("bounds[north]", String(params.bounds.north));
    searchParams.set("bounds[east]", String(params.bounds.east));
    searchParams.set("bounds[west]", String(params.bounds.west));
  }
  if (params?.category) searchParams.set("category", params.category);
  if (params?.purpose) searchParams.set("purpose", params.purpose);
  if (params?.city_id) searchParams.set("city_id", params.city_id);
  const qs = searchParams.toString();
  return fetchInstance<{ data: MapPin[] }>(
    `/classified/map-pins${qs ? "?" + qs : ""}`,
  );
};
