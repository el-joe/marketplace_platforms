"use client";

import { useCallback, useState } from "react";
import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";

export type QueryParam = {
  filterBy: string;
  query: string;
};

const useApiFilter = () => {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [filters, setFilters] = useState<QueryParam[]>(
    Array.from(searchParams.entries()).map(([filterBy, query]) => ({
      filterBy,
      query,
    })),
  );
  const setFilter = (newFilter: QueryParam) => {
    if (filters.find((f) => f.filterBy === newFilter.filterBy)) {
      return setFilters((p) => [
        ...p.filter((f) => f.filterBy !== newFilter.filterBy),
        newFilter,
      ]);
    }

    setFilters((p) => [...p, newFilter]);
  };

  const applyFilter = (newFilter?: QueryParam) => {
    if (!filters.length && !newFilter) return;
    const params = new URLSearchParams(searchParams.toString());
    if (!!newFilter) setFilter(newFilter);
    [...filters, newFilter]
      .filter((f) => f !== undefined)
      .forEach(({ filterBy, query }) => {
        const value = query?.trim();

        if (!value) {
          params.delete(filterBy);
          setFilters((p) => [...p.filter((f) => f.filterBy !== filterBy)]);
          return;
        }

        params.set(filterBy, value);
      });

    const queryString = params.toString();

    router.push(queryString ? `${pathname}?${queryString}` : pathname);
  };

  const getFiltersString = (newFilter?: QueryParam) => {
    const params = new URLSearchParams(searchParams.toString());
    if (!!newFilter) setFilter(newFilter);
    [...filters, newFilter]
      .filter((f) => f !== undefined)
      .forEach(({ filterBy, query }) => {
        const value = query?.trim();

        if (!value) {
          params.delete(filterBy);
          setFilters((p) => [...p.filter((f) => f.filterBy !== filterBy)]);
          return;
        }

        params.set(filterBy, value);
      });
    const queryString = params.toString();
    return queryString;
  };

  const removeAllFilters = useCallback(
    (except?: string[]) => {
      const params = new URLSearchParams(searchParams.toString());

      Array.from(params.keys())
        .filter((key) => !except?.includes(key))
        .forEach((key) => params.delete(key));

      const queryString = params.toString();

      router.push(queryString ? `${pathname}?${queryString}` : pathname);
      setFilters([]);
    },
    [pathname, router, searchParams],
  );

  return {
    filters,
    applyFilter,
    setFilter,
    removeAllFilters,
    getFiltersString,
  };
};

export default useApiFilter;
