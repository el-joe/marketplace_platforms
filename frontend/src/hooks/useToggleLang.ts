"use client";
// import { getLanguage } from "@/src/helpers/handleRegionAndLocal";
import { usePathname, useRouter } from "@/i18n/navigation";
// import { useLocale } from "next-intl";
import { useSearchParams } from "next/navigation";
import useLocale from "./use-locale";
import { getCookie } from "cookies-next/client";

const useToggleLang = () => {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const country = getCookie("country");

  const changeLanguage = (newLocale: "en" | "ar") => {
    const currentParams = new URLSearchParams(Array.from(params.entries()));
    router.replace(`${pathname}?${currentParams}`, {
      locale: `${country}-${newLocale}`,
    });
  };
  const handleToggleLang = () => {
    changeLanguage(locale === "en" ? "ar" : "en");
  };
  return handleToggleLang;
};

export default useToggleLang;
