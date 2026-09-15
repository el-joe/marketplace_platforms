"use client";
// import { getLanguage } from "@/src/helpers/handleRegionAndLocal";
import { usePathname, useRouter } from "@/i18n/navigation";
// import { useLocale } from "next-intl";
import { useSearchParams } from "next/navigation";
import useLocale from "./use-locale";
import { getCookie } from "cookies-next/client";

const useHandleLocale = () => {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const country = getCookie("country") as string;

  const changeLocale = (newLang: "en" | "ar", newCountry: string) => {
    const currentParams = new URLSearchParams(Array.from(params.entries()));
    router.replace(`${pathname}?${currentParams}`, {
      locale: `${newCountry}-${newLang}`,
    });
  };
  const handleToggleLang = () => {
    changeLocale(locale === "en" ? "ar" : "en", country?.toLowerCase());
  };
  const handleChangeCountry = (newCountry: string) => {
    changeLocale(locale, newCountry);
  };
  return { handleToggleLang, handleChangeCountry };
};

export default useHandleLocale;
