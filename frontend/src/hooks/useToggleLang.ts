"use client";
// import { getLanguage } from "@/src/helpers/handleRegionAndLocal";
import { usePathname, useRouter } from "@/i18n/navigation";
import { region } from "@/src/utils/region";
// import { useLocale } from "next-intl";
import { useSearchParams } from "next/navigation";
import useLocale from "./use-locale";

const useToggleLang = () => {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const changeLanguage = (newLocale: "en" | "ar") => {
    const currentParams = new URLSearchParams(Array.from(params.entries()));
    router.replace(`${pathname}?${currentParams}`, {
      locale: `${region}-${newLocale}`,
    });
  };
  const handleToggleLang = () => {
    changeLanguage(locale === "en" ? "ar" : "en");
  };
  return handleToggleLang;
};

export default useToggleLang;
