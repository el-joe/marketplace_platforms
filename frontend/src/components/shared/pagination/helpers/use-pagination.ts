"use client";

import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import { useNavigationLoadingContext } from "@/src/providers/navigation-loading-provider";

const PAGE_PARAM = "page";

const usePagination = () => {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { startNavigationLoading } = useNavigationLoadingContext();

  const currentPage = Number(searchParams.get(PAGE_PARAM)) || 1;

  const goToPage = (page: number) => {
    const params = new URLSearchParams(searchParams.toString());
    params.set(PAGE_PARAM, String(page));
    startNavigationLoading();
    router.push(`${pathname}?${params.toString()}`);
  };

  return { currentPage, goToPage };
};

export default usePagination;
