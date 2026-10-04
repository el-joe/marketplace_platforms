import { fetchInstance } from "@/src/lib/utils";
import { IClassifiedCategoriesList, IClassifiedsList } from "../helpers/types";

export const getClassifiedsService = ({
  category = "all",
}: {
  category?: string;
}) =>
  fetchInstance<{ data: IClassifiedsList }>(`/browse/classified/${category}`, {
    method: "GET",
  });

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
}) => {
  const searchParams = new URLSearchParams();
  if (params?.bounds) {
    searchParams.set('bounds[south]', String(params.bounds.south));
    searchParams.set('bounds[north]', String(params.bounds.north));
    searchParams.set('bounds[east]',  String(params.bounds.east));
    searchParams.set('bounds[west]',  String(params.bounds.west));
  }
  if (params?.category) searchParams.set('category', params.category);
  if (params?.purpose)  searchParams.set('purpose', params.purpose);
  if (params?.city_id)  searchParams.set('city_id', params.city_id);
  const qs = searchParams.toString();
  return fetchInstance<{ data: MapPin[] }>(`/classified/map-pins${qs ? '?' + qs : ''}`);
};
