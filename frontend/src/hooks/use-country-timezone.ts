"use client";

import { useQuery } from "@tanstack/react-query";
import { getCountriesService } from "../services/countries";
import useRegion from "./use-region";

/**
 * IANA timezone of the storefront country in the URL (e.g. "uae" -> "Asia/Dubai").
 * Shares the cached ["countries"] query with the header, so it costs no extra request.
 * `undefined` until the countries list has loaded.
 */
export default function useCountryTimezone(): string | undefined {
  const region = useRegion();
  const { data } = useQuery({
    queryKey: ["countries"],
    queryFn: getCountriesService,
    staleTime: 60 * 60 * 1000,
  });

  return data?.data?.find((country) => country.site_code === region)?.timezone;
}
