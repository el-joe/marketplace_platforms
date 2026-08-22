import { region } from "@/src/utils/region";
import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  // A list of all locales that are supported
  locales: [`${region}-en`, `${region}-ar`],
  // for SEO
  localePrefix: "always",

  // Used when no locale matches
  defaultLocale: `${region}-en`,
});
