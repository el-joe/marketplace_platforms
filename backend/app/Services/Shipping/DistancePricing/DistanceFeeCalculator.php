<?php

namespace App\Services\Shipping\DistancePricing;

use App\Enums\DistanceDeliveryStatus;

/**
 * Pure distance-based delivery fee math (client item #19). No database, no framework.
 *
 * Precedence for one origin → customer leg:
 *   1. coordinates missing on either side      → Fallback (normal zone pricing)
 *   2. distance > max service radius            → Unavailable
 *   3. order-amount free-shipping threshold met → FreeByOrderAmount
 *   4. distance <= free radius                  → FreeWithinRadius
 *   5. a tier covers the distance               → Tier (from inclusive, to exclusive, last may be open)
 *   6. otherwise (gap / beyond last closed tier) → Fallback
 *
 * Distances are straight-line (haversine) and rounded to 0.1 km before any comparison, so the
 * number shown to the customer is exactly the number that was priced.
 */
final class DistanceFeeCalculator
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public static function haversineKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    public static function roundKm(float $km): float
    {
        return self::toTenths($km) / 10;
    }

    /**
     * Integer tenths of a km, so boundary comparisons never suffer float drift (3.0 vs 2.9999999).
     */
    public static function toTenths(float $km): int
    {
        return (int) round($km * 10);
    }

    /**
     * @param  array{0: float, 1: float}|null  $origin  [lat, lng]
     * @param  array{0: float, 1: float}|null  $destination  [lat, lng]
     */
    public static function distanceBetween(?array $origin, ?array $destination): ?float
    {
        if ($origin === null || $destination === null) {
            return null;
        }

        return self::roundKm(self::haversineKm($origin[0], $origin[1], $destination[0], $destination[1]));
    }

    public function quote(DistanceRule $rule, ?float $distanceKm, bool $orderAmountThresholdMet): DistanceQuote
    {
        if ($distanceKm === null) {
            return new DistanceQuote(DistanceDeliveryStatus::Fallback, null, fallbackReason: DistanceQuote::REASON_MISSING_COORDINATES);
        }

        $distanceKm = self::roundKm($distanceKm);
        $tenths = self::toTenths($distanceKm);

        if ($rule->maxServiceRadiusKm !== null && $tenths > self::toTenths($rule->maxServiceRadiusKm)) {
            return new DistanceQuote(DistanceDeliveryStatus::Unavailable, $distanceKm);
        }

        if ($orderAmountThresholdMet) {
            return new DistanceQuote(DistanceDeliveryStatus::FreeByOrderAmount, $distanceKm);
        }

        if ($rule->freeRadiusKm !== null && $tenths <= self::toTenths($rule->freeRadiusKm)) {
            return new DistanceQuote(DistanceDeliveryStatus::FreeWithinRadius, $distanceKm);
        }

        $tier = $rule->tierFor($distanceKm);
        if ($tier !== null) {
            return new DistanceQuote(DistanceDeliveryStatus::Tier, $distanceKm, $tier->price, $tier);
        }

        return new DistanceQuote(DistanceDeliveryStatus::Fallback, $distanceKm, fallbackReason: DistanceQuote::REASON_NO_MATCHING_TIER);
    }

    /**
     * Structural check of an admin-entered tier list.
     *
     * Errors block saving (overlap, inverted band, open-ended band that is not last, more than one open band,
     * free radius not below max radius). Warnings are informational (gaps, first tier not starting at the free
     * radius / 0): distances that fall into a gap use normal zone pricing.
     *
     * @param  list<array{from_km: float|int|string, to_km: float|int|string|null, price?: mixed}>  $tiers
     * @return array{errors: list<array{key: string, params: array<string, mixed>}>, warnings: list<array{key: string, params: array<string, mixed>}>}
     */
    public static function analyseTiers(array $tiers, ?float $freeRadiusKm = null, ?float $maxServiceRadiusKm = null): array
    {
        $errors = [];
        $warnings = [];

        if ($freeRadiusKm !== null && $maxServiceRadiusKm !== null && self::toTenths($freeRadiusKm) >= self::toTenths($maxServiceRadiusKm)) {
            $errors[] = ['key' => 'free_radius_not_below_max', 'params' => []];
        }

        $bands = [];
        foreach (array_values($tiers) as $index => $tier) {
            $from = (float) $tier['from_km'];
            $to = ($tier['to_km'] ?? null) === null || $tier['to_km'] === '' ? null : (float) $tier['to_km'];
            if ($to !== null && self::toTenths($to) <= self::toTenths($from)) {
                $errors[] = ['key' => 'tier_inverted', 'params' => ['row' => $index + 1]];
            }
            $bands[] = ['row' => $index + 1, 'from' => self::toTenths($from), 'to' => $to === null ? null : self::toTenths($to)];
        }

        usort($bands, fn (array $a, array $b) => $a['from'] <=> $b['from']);

        $openEnded = array_values(array_filter($bands, fn (array $band) => $band['to'] === null));
        if (count($openEnded) > 1) {
            $errors[] = ['key' => 'tier_multiple_open_ended', 'params' => []];
        } elseif (count($openEnded) === 1 && $openEnded[0] !== end($bands)) {
            $errors[] = ['key' => 'tier_open_ended_not_last', 'params' => ['row' => $openEnded[0]['row']]];
        }

        $start = $freeRadiusKm !== null ? self::toTenths($freeRadiusKm) : 0;
        if ($bands !== [] && $bands[0]['from'] > $start) {
            $warnings[] = ['key' => 'tier_gap', 'params' => ['from' => $start / 10, 'to' => $bands[0]['from'] / 10]];
        }

        for ($i = 1, $n = count($bands); $i < $n; $i++) {
            $previous = $bands[$i - 1];
            $current = $bands[$i];

            if ($previous['to'] === null || $current['from'] < $previous['to']) {
                $errors[] = ['key' => 'tier_overlap', 'params' => ['a' => $previous['row'], 'b' => $current['row']]];
            } elseif ($current['from'] > $previous['to']) {
                $warnings[] = ['key' => 'tier_gap', 'params' => ['from' => $previous['to'] / 10, 'to' => $current['from'] / 10]];
            }
        }

        if ($maxServiceRadiusKm !== null && $bands !== []) {
            $last = end($bands);
            $max = self::toTenths($maxServiceRadiusKm);
            if ($last['to'] !== null && $last['to'] <= $max) {
                $warnings[] = ['key' => 'tier_gap', 'params' => ['from' => $last['to'] / 10, 'to' => $max / 10]];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
