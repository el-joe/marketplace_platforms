"use client";

import { useQuery, type UseQueryResult } from "@tanstack/react-query";
import { ApiRequestError } from "../lib/utils";
import { getCurrenciesService, ICurrency } from "../services/currencies";

export const CURRENCIES_QUERY_KEY = ["currencies"] as const;

/** Active currencies with their display symbol. Rarely changes — cached for the session. */
export function useCurrencies(): UseQueryResult<ICurrency[], ApiRequestError> {
  return useQuery({
    queryKey: CURRENCIES_QUERY_KEY,
    queryFn: async () => {
      const { data } = await getCurrenciesService();
      return data;
    },
    staleTime: 60 * 60 * 1000,
    gcTime: 24 * 60 * 60 * 1000,
  });
}
