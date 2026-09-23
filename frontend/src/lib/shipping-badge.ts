type Loc =
  | { ar?: string | string[] | null; en?: string | string[] | null }
  | null
  | undefined;

export interface ShippingBadgeLike {
  label?: Loc;
  show_delivery_time?: boolean;
  delivery_text?: Loc;
  delivery_days_min?: number | null;
  delivery_days_max?: number | null;
}

const pick = (v: Loc, locale: string) => {
  const other = locale === "ar" ? "en" : "ar";
  const str = (x: unknown) =>
    typeof x === "string" && x.trim() ? x.trim() : null;
  return str(v?.[locale as "ar" | "en"]) || str(v?.[other]) || null;
};

/** Returns delivery_text when enabled, else the method label; null if neither. */
export function getShippingBadgeText(
  badge: ShippingBadgeLike | null | undefined,
  locale: string,
): string | null {
  if (!badge) return null;
  if (badge.show_delivery_time) {
    const t = pick(badge.delivery_text, locale);
    if (t) return t;
  }
  return pick(badge.label, locale);
}
