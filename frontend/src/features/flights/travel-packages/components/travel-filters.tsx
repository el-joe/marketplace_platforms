"use client";

import { useCallback, useMemo } from "react";
import { useRouter, usePathname, useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { format } from "date-fns";
import type { DateRange } from "react-day-picker";
import { Select } from "@/src/components/ui/base-inputs/select";
import DateRangePicker from "@/src/components/shared/date-range-picker";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import type { CityOption, DestinationOption } from "../helpers/build-destination-options";

type Props = {
  countries: DestinationOption[];
  cities: CityOption[];
};

const ALL_VALUE = "__all__";

export default function TravelFilters({ countries, cities }: Props) {
  const t = useTranslations("flights.filters");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const countryId = searchParams.get("country_id") ?? "";
  const cityId = searchParams.get("city_id") ?? "";
  const dateFrom = searchParams.get("date_from");
  const dateTo = searchParams.get("date_to");

  const updateParams = useCallback(
    (updates: Record<string, string | null>) => {
      const params = new URLSearchParams(searchParams.toString());
      for (const [key, value] of Object.entries(updates)) {
        if (value) {
          params.set(key, value);
        } else {
          params.delete(key);
        }
      }
      params.delete("page");
      router.push(`${pathname}?${params.toString()}`);
    },
    [router, pathname, searchParams],
  );

  const availableCities = useMemo(
    () => (countryId ? cities.filter((city) => city.country_id === countryId) : cities),
    [cities, countryId],
  );

  const dateRange: DateRange | undefined = dateFrom
    ? {
        from: new Date(dateFrom),
        to: dateTo ? new Date(dateTo) : undefined,
      }
    : undefined;

  const hasFilters = countryId || cityId || dateFrom || dateTo;

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Select
        placeholder={t("allCountries")}
        value={countryId || ALL_VALUE}
        onValueChange={(value) =>
          updateParams({
            country_id: value && value !== ALL_VALUE ? value : null,
            city_id: null,
          })
        }
        items={[
          { label: t("allCountries"), value: ALL_VALUE },
          ...countries.map((country) => ({
            label: locale === "ar" ? country.name_ar : country.name_en,
            value: country.id,
          })),
        ]}
      />

      <Select
        placeholder={t("allCities")}
        value={cityId || ALL_VALUE}
        onValueChange={(value) =>
          updateParams({ city_id: value && value !== ALL_VALUE ? value : null })
        }
        items={[
          { label: t("allCities"), value: ALL_VALUE },
          ...availableCities.map((city) => ({
            label: locale === "ar" ? city.name_ar : city.name_en,
            value: city.id,
          })),
        ]}
      />

      <DateRangePicker
        value={dateRange}
        placeholder={t("dateRange")}
        onChange={(range) =>
          updateParams({
            date_from: range?.from ? format(range.from, "yyyy-MM-dd") : null,
            date_to: range?.to ? format(range.to, "yyyy-MM-dd") : null,
          })
        }
      />

      {hasFilters && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() =>
            updateParams({
              country_id: null,
              city_id: null,
              date_from: null,
              date_to: null,
            })
          }
        >
          {t("clearFilters")}
        </Button>
      )}
    </div>
  );
}
