import { getRequestConfig } from "next-intl/server";
import { hasLocale } from "next-intl";
import { getRouting } from "./routing";
import { getLanguage } from "@/src/helpers/handleRegionAndLocal";

export default getRequestConfig(async ({ requestLocale }) => {
  // Typically corresponds to the `[locale]` segment
  const requested = await requestLocale;
  const routing = await getRouting();
  const locale = hasLocale(routing.locales, requested)
    ? requested
    : routing.defaultLocale;

  return {
    locale,
    messages: (await import(`../locale/${getLanguage(locale)}.json`)).default,
  };
});
