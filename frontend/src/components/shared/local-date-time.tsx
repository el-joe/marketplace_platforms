"use client";

import { useSyncExternalStore } from "react";
import useLocale from "@/src/hooks/use-locale";
import useCountryTimezone from "@/src/hooks/use-country-timezone";
import { formatInTimeZone } from "@/src/helpers/format-in-time-zone";

type Props = {
  /** ISO-8601 timestamp from the API (with its offset). */
  value: string;
  /** Show the time and the timezone (default), or the date only. */
  withTime?: boolean;
  /** Custom Intl fields, e.g. { day: "numeric", month: "short" }; the country timezone is always applied. */
  format?: Intl.DateTimeFormatOptions;
  className?: string;
};

const subscribe = () => () => {};

/**
 * A timestamp in the storefront country's timezone (countries.timezone, e.g. Asia/Dubai for /uae),
 * with the timezone named, e.g. "6 Nov 2026, 19:43 GMT+4".
 * Rendered on the client only, once the country's timezone is known.
 */
export default function LocalDateTime({ value, withTime = true, format, className }: Props) {
  const locale = useLocale();
  const timeZone = useCountryTimezone();
  const isClient = useSyncExternalStore(subscribe, () => true, () => false);
  const formatted = isClient && timeZone ? formatInTimeZone(value, { locale, timeZone, withTime, format }) : null;

  return (
    <time dateTime={value} className={className} dir="auto" suppressHydrationWarning>
      {formatted ?? " "}
    </time>
  );
}
