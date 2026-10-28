"use client";

import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import { useNavigationLoadingContext } from "@/src/providers/navigation-loading-provider";

const useMarketerFilter = () => {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { startNavigationLoading } = useNavigationLoadingContext();

  const setFilters = (entries: [key: string, value: string][]) => {
    const params = new URLSearchParams(searchParams.toString());

    entries.forEach(([key, value]) => {
      const trimmedValue = value?.trim();

      if (!trimmedValue) {
        params.delete(key);
      } else {
        params.set(key, trimmedValue);
      }
    });

    // Any filter/tab/sort change starts again from the first page.
    if (!entries.some(([key]) => key === "page")) params.delete("page");

    const queryString = params.toString();
    startNavigationLoading();
    router.push(queryString ? `${pathname}?${queryString}` : pathname);
  };

  const setFilter = (key: string, value: string) => setFilters([[key, value]]);

  return { setFilter, setFilters };
};

export default useMarketerFilter;
