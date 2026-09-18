import type { TravelPackageSummary } from "../../helpers/types";

export type DestinationOption = {
  id: string;
  name_en: string;
  name_ar: string;
};

export type CityOption = DestinationOption & {
  country_id: string;
};

/**
 * Derives unique country/city filter options from a set of (unfiltered,
 * category-scoped) travel package summaries. There is no dedicated
 * country/city listing endpoint for the customer browse API, so options are
 * built from the destinations actually present on packages returned by
 * `GET /browse/travel/:id`.
 */
export function buildDestinationOptions(items: TravelPackageSummary[]): {
  countries: DestinationOption[];
  cities: CityOption[];
} {
  const countries = new Map<string, DestinationOption>();
  const cities = new Map<string, CityOption>();

  for (const item of items) {
    const destination = item.destination;
    if (!destination) continue;

    if (destination.country_id) {
      countries.set(destination.country_id, {
        id: destination.country_id,
        name_en: destination.country_en ?? item.destination_country,
        name_ar: destination.country_ar ?? item.destination_country,
      });
    }

    if (destination.city_id && destination.country_id) {
      cities.set(destination.city_id, {
        id: destination.city_id,
        name_en: destination.city_en ?? item.destination_city,
        name_ar: destination.city_ar ?? item.destination_city,
        country_id: destination.country_id,
      });
    }
  }

  return {
    countries: Array.from(countries.values()),
    cities: Array.from(cities.values()),
  };
}
