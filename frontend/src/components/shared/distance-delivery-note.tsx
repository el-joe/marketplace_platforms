"use client";
import { useTranslations } from "next-intl";
import Price from "@/src/components/shared/Price";
import { cn } from "@/src/lib/utils";
import type { DistancePricing } from "@/types/cart.type";

type Props = { pricing: DistancePricing | null | undefined; className?: string };

const km = (value: number | null) => (value ?? 0).toFixed(1);

/**
 * Distance-based delivery line for a cart / checkout shipping group:
 * "Free delivery within 3 km · 2.4 km away", "4.2 km · delivery 1.500", or why the method is unavailable.
 * Hidden when the zone has no distance rule or the API fell back to normal zone pricing.
 */
export default function DistanceDeliveryNote({ pricing, className }: Props) {
  const t = useTranslations("distanceDelivery");
  if (!pricing || pricing.status === "fallback") return null;

  const distance = km(pricing.distance_km);
  const base = "flex items-center gap-1 text-xs md:text-sm font-medium";

  switch (pricing.status) {
    case "unavailable":
      return (
        <p className={cn(base, "text-red-600", className)}>
          {t("unavailable", { distance, max: km(pricing.max_service_radius_km) })}
        </p>
      );
    case "free_within_radius":
      return (
        <p className={cn(base, "text-green", className)}>
          {t("freeWithinRadius", { radius: km(pricing.free_radius_km), distance })}
        </p>
      );
    case "free_by_order_amount":
      return (
        <p className={cn(base, "text-green", className)}>
          {t("freeByOrderAmount", { distance })}
        </p>
      );
    case "tier":
      return (
        <div className={cn(base, className)}>
          <span>{t("tier", { distance })}</span>
          <Price currentPrice={pricing.fee} size="xs" />
        </div>
      );
    default:
      return null;
  }
}
