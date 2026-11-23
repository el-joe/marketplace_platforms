/**
 * Formats an API delivery day ("YYYY-MM-DD") for "Get it by {date}", e.g. "Thu, 26 Nov" / "الخميس، ٢٦ نوفمبر".
 * Parsed as a calendar day (no timezone shift). Returns null for missing/invalid input so callers can hide the line.
 */
export function formatDeliveryDate(
  ymd: string | null | undefined,
  locale: "ar" | "en",
): string | null {
  const match = ymd?.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return null;
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  if (Number.isNaN(date.getTime())) return null;

  return new Intl.DateTimeFormat(locale === "ar" ? "ar-EG" : "en-GB", {
    weekday: "short",
    day: "numeric",
    month: "short",
  }).format(date);
}
