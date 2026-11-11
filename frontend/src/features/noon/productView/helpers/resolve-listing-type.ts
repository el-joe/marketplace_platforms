export type CartListingType = "vendor" | "admin" | "marketer";

/**
 * Maps the `listing_type` returned by the product-detail / wishlist APIs to the
 * `listing_type` the cart endpoint expects, so admin and marketer listings are
 * not looked up as vendor listings.
 */
export function resolveListingType(
  listing?: { listing_type?: string | null } | null,
): CartListingType {
  const type = listing?.listing_type?.replace(/_listing$/, "");
  if (type === "marketer") return "marketer";
  if (type === "admin") return "admin";
  // Never infer from `is_admin_listing`: it is also true for Express-FBN
  // vendor listings, which must be added as vendor items.
  return "vendor";
}
