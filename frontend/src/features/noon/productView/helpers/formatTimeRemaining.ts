import { differenceInMinutes, parse } from "date-fns";
import { _Translator } from "next-intl";

export default function formatTimeRemaining(
  dateString: string,
  t: _Translator<Record<string, string>>,
): string {
  const targetDate = parse(dateString, "yyyy-MM-dd", new Date());
  const now = new Date();

  const totalMinutes = differenceInMinutes(targetDate, now);

  if (totalMinutes >= 48 * 60) {
    return t(`$day`, { value: Math.ceil(totalMinutes / 60 / 24) });
  }

  if (totalMinutes >= 24 * 60) {
    return t("tomorrow");
  }

  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours === 0) return `${minutes} MIN`;
  if (minutes === 0) return `${hours} HR`;
  return t(`$hr$min`, { hr: hours, min: minutes });
}
