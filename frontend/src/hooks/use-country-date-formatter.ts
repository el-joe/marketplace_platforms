"use client";

import { useCallback } from "react";
import { formatInTimeZone } from "../helpers/format-in-time-zone";
import useCountryTimezone from "./use-country-timezone";
import useLocale from "./use-locale";

/**
 * Formatter for API timestamps in the storefront country's timezone, for places that need a
 * string (e.g. inside t() interpolation). Prefer <LocalDateTime> when rendering an element.
 * Returns "" until the country's timezone is known.
 */
export default function useCountryDateFormatter() {
  const locale = useLocale();
  const timeZone = useCountryTimezone();

  return useCallback(
    (value: string | null | undefined, format?: Intl.DateTimeFormatOptions) =>
      value && timeZone ? formatInTimeZone(value, { locale, timeZone, format }) : "",
    [locale, timeZone],
  );
}
