"use client";

import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import { useNavigationLoadingContext } from "@/src/providers/navigation-loading-provider";

const useShopFilterParams = () => {
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

    const queryString = params.toString();
    startNavigationLoading();
    router.push(queryString ? `${pathname}?${queryString}` : pathname);
  };

  const setFilter = (key: string, value: string) => setFilters([[key, value]]);

  return { setFilter, setFilters };
};

export default useShopFilterParams;
