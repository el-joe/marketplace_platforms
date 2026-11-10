import type { Bilingual } from "./types";

/** Requested language, falling back to the other one when empty (mirrors StaticPage::localized()). */
export function localized(value: Bilingual, locale: "en" | "ar"): string {
  const other = locale === "ar" ? "en" : "ar";
  return value[locale] || value[other] || "";
}
