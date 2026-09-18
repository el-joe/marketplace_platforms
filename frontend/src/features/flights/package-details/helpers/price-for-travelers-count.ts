type Tier = { travelers_count: number; price: number };

/**
 * Mirrors `TravelPackage::priceForTravelersCount()` (backend/app/Models/TravelPackage.php)
 * so the sidebar can show the correct total before submitting: an exact
 * pricing-tier match wins, otherwise it falls back to per-person price * count.
 */
export function priceForTravelersCount(
  travelersCount: number,
  perPersonPrice: number,
  tiers: Tier[] | null,
): number {
  const tier = tiers?.find((t) => t.travelers_count === travelersCount);
  if (tier) return tier.price;

  return perPersonPrice * travelersCount;
}
