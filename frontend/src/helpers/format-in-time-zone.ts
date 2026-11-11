type Options = {
  locale: string;
  /** IANA timezone, e.g. "Asia/Dubai" (see useCountryTimezone). */
  timeZone: string;
  /** Include the time and the zone name (default true), or the date only. */
  withTime?: boolean;
  /** Custom Intl fields (replaces the default date/time fields; the timezone is always applied). */
  format?: Intl.DateTimeFormatOptions;
};

/**
 * Format an API timestamp (ISO-8601, UTC) in a country's timezone.
 * Returns the raw value when it is not a valid date.
 */
export function formatInTimeZone(
  value: string,
  { locale, timeZone, withTime = true, format }: Options,
): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  const fields: Intl.DateTimeFormatOptions = format ?? {
    year: "numeric",
    month: "short",
    day: "numeric",
    ...(withTime
      ? { hour: "2-digit", minute: "2-digit", hourCycle: "h23", timeZoneName: "short" }
      : {}),
  };

  return new Intl.DateTimeFormat(locale === "ar" ? "ar-EG-u-nu-latn" : "en-GB", {
    ...fields,
    timeZone,
  }).format(date);
}
