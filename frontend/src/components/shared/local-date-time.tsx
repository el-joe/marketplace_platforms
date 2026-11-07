"use client";

import { useSyncExternalStore } from "react";
import useLocale from "@/src/hooks/use-locale";

type Props = {
  /** ISO-8601 timestamp from the API (with its offset). */
  value: string;
  /** Show the time and the viewer's timezone (default), or the date only. */
  withTime?: boolean;
  className?: string;
};

const subscribe = () => () => {};

/**
 * A timestamp in the viewer's own (browser) timezone, with the timezone named, e.g. "6 Nov 2026, 19:43 GMT+2".
 * Rendered on the client only: the server runs in UTC and does not know the viewer's timezone.
 */
export default function LocalDateTime({ value, withTime = true, className }: Props) {
  const locale = useLocale();
  const formatted = useSyncExternalStore(
    subscribe,
    () => formatLocal(value, locale, withTime),
    () => null,
  );

  return (
    <time dateTime={value} className={className} dir="auto" suppressHydrationWarning>
      {formatted ?? " "}
    </time>
  );
}

function formatLocal(value: string, locale: string, withTime: boolean): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat(locale === "ar" ? "ar-EG-u-nu-latn" : "en-GB", {
    year: "numeric",
    month: "short",
    day: "numeric",
    ...(withTime
      ? { hour: "2-digit", minute: "2-digit", hourCycle: "h23", timeZoneName: "short" }
      : {}),
  }).format(date);
}
