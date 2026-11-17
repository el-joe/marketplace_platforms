import { ApplicableCoupon, CartItem } from "@/types/cart.type";

/**
 * Every coupon that applies to at least one cart item, once each.
 *
 * The API attaches applicable coupons per cart item; the "View available
 * offers" sheet used to read only the first item's list, so offers for the
 * other items never showed (and the sheet looked empty). When the same coupon
 * applies to several items, the highest estimated saving is kept.
 */
export function getAvailableCoupons(items: CartItem[] = []): ApplicableCoupon[] {
  const byCode = new Map<string, ApplicableCoupon>();

  for (const item of items) {
    for (const coupon of item.applicable_coupons ?? []) {
      if (!coupon?.code) continue;
      const existing = byCode.get(coupon.code);
      if (
        !existing ||
        (coupon.estimated_saving ?? 0) > (existing.estimated_saving ?? 0)
      ) {
        byCode.set(coupon.code, coupon);
      }
    }
  }

  return [...byCode.values()].sort(
    (a, b) => (b.estimated_saving ?? 0) - (a.estimated_saving ?? 0),
  );
}
