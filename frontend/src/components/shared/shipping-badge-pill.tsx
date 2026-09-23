import {
  ChevronRight,
  Clock,
  Gift,
  Package,
  Plane,
  Rocket,
  ShieldCheck,
  Star,
  Tag,
  Truck,
  Zap,
  type LucideIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import {
  getShippingBadgeText,
  type ShippingBadgeLike,
} from "@/src/lib/shipping-badge";
import { cn } from "@/src/lib/utils";

const ICONS: Record<string, LucideIcon> = {
  bolt: Zap,
  truck: Truck,
  clock: Clock,
  rocket: Rocket,
  box: Package,
  plane: Plane,
  star: Star,
  gift: Gift,
  "shield-check": ShieldCheck,
  tag: Tag,
};
const FILLED = new Set(["bolt", "star"]);

type Badge = ShippingBadgeLike & {
  icon?: string | null;
  badge_image_url?: string | null;
  color_hex?: string;
  text_color_hex?: string;
};

export function ShippingBadgePill({
  badge,
  locale,
  className,
}: {
  badge: Badge;
  locale: string;
  className?: string;
}) {
  const t = useTranslations();
  const key = badge.icon === undefined ? "bolt" : badge.icon;
  const Icon = key ? ICONS[key] : undefined;
  let text = getShippingBadgeText(badge, locale);
  if (!text) {
    const days = badge.delivery_days_min ?? badge.delivery_days_max;
    text = `${t("getIn")} ${days != null ? t("$day", { value: days }) : ""}`;
  }
  if (badge.badge_image_url) {
    return (
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={badge.badge_image_url}
        alt={text}
        className={cn("max-h-4 lg:max-h-5 w-auto max-w-full object-contain", className)}
      />
    );
  }
  return (
    <div
      className={cn(
        "flex w-fit max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[9px] lg:text-xs font-semibold",
        className,
      )}
      style={{ background: badge.color_hex, color: badge.text_color_hex }}
    >
      {Icon && (
        <Icon
          className={cn(
            "size-3 lg:size-4 shrink-0",
            key && FILLED.has(key) && "fill-current",
          )}
        />
      )}
      <span className="truncate min-w-0">{text}</span>
      <ChevronRight className="size-3 lg:size-4 shrink-0 rtl:rotate-180" />
    </div>
  );
}
