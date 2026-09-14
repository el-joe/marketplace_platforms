import resolveCookie from "@/src/helpers/resolveCookie";
import { getCountriesService } from "@/src/services/countries";
import { defineRouting } from "next-intl/routing";

export const getRouting = async () => {
  const [country = "uae", supportedCountriesData] = await Promise.all([
    resolveCookie("country"),
    getCountriesService(),
  ]);
  const supportedCountries = supportedCountriesData.data.map(
    (sc) => sc.site_code,
  );
  return defineRouting({
    // A list of all locales that are supported
    locales: supportedCountries.flatMap((r) => [`${r}-en`, `${r}-ar`]),
    // for SEO
    localePrefix: "always",

    // Used when no locale matches
    defaultLocale: `${country}-en`,
  });
};
