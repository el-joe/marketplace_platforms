import { icons, TagIcon } from "lucide-react";
import type { ComponentProps } from "react";
import type AnimatedBadge from "@/src/components/shared/animated-badge";

import type { PromoBadge } from "@/types/globals";
export type { PromoBadge };

type AnimatedBadgeItems = ComponentProps<typeof AnimatedBadge>["badges"];

const toPascal = (key: string) =>
  key
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join("");

/** Maps API promo_badges to AnimatedBadge `badges` props. */
export function mapPromoBadges(
  badges: PromoBadge[] | null | undefined,
  locale: "ar" | "en" | string,
): AnimatedBadgeItems {
  return (badges ?? []).map((badge) => ({
    label:
      badge.label?.[locale as "ar" | "en"] ?? badge.label?.en ?? "",
    icon:
      icons[badge.icon_key as keyof typeof icons] ??
      icons[toPascal(badge.icon_key) as keyof typeof icons] ??
      TagIcon,
    iconColor: badge.color_hex,
  }));
}
