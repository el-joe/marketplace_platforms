"use client";
import { useSyncExternalStore } from "react";
import { useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";

const TICK_MS = 30_000;

function subscribe(onTick: () => void) {
  const id = setInterval(onTick, TICK_MS);
  return () => clearInterval(id);
}
// Snapshot only changes once per tick, so React re-renders at most every 30s.
const getSnapshot = () => Math.floor(Date.now() / TICK_MS) * TICK_MS;
// Server render has no clock: render nothing and let the client fill it in (no hydration mismatch).
const getServerSnapshot = () => null;

type Props = { cutoffAt: string | null | undefined; className?: string };

/** "Order within 3 hrs 12 mins" driven by the API's order cutoff; hidden when absent or elapsed. */
export default function OrderCutoffCountdown({ cutoffAt, className }: Props) {
  const t = useTranslations("deliveryEstimate");
  const now = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);

  const cutoff = cutoffAt ? Date.parse(cutoffAt) : NaN;
  if (now === null || Number.isNaN(cutoff)) return null;

  const totalMinutes = Math.floor((cutoff - now) / 60_000);
  if (totalMinutes <= 0) return null;

  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  return (
    <p className={cn("text-sm text-gray", className)}>
      {hours > 0
        ? t("orderWithin", { hours, minutes })
        : t("orderWithinMinutes", { minutes })}
    </p>
  );
}
