<?php

namespace App\Services;

use App\Models\FlashSalePriceHistory;
use App\Models\FlashSaleSubmission;

class FakeDiscountDetectionService
{
    /**
     * Analyze a submission for potential fake-discount manipulation.
     *
     * Returns: {
     *   is_suspect: bool,
     *   confidence: float (0-1),
     *   reasons: string[],
     *   average_price_30d: int|null,
     *   average_price_30d_formatted: string,
     *   price_history_count: int,
     *   checks: { is_price_barely_below_avg, is_price_recently_inflated, discount_is_artificial }
     * }
     */
    public function analyze(FlashSaleSubmission $submission): array
    {
        $flashPrice    = (int) $submission->flash_price;
        $originalPrice = (int) $submission->original_price;

        $history = FlashSalePriceHistory::query()
            ->when(
                $submission->admin_listing_id !== null,
                fn($q) => $q->where('admin_listing_id', $submission->admin_listing_id),
                fn($q) => $q->where('vendor_listing_id', $submission->vendor_listing_id)
            )
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get();

        if ($history->isEmpty()) {
            return [
                'is_suspect'                 => false,
                'confidence'                 => 0.0,
                'reasons'                    => [],
                'average_price_30d'          => null,
                'average_price_30d_formatted' => 'N/A',
                'price_history_count'        => 0,
                'checks' => [
                    'is_price_barely_below_avg'   => false,
                    'is_price_recently_inflated'  => false,
                    'discount_is_artificial'      => false,
                ],
            ];
        }

        $avgPrice30d = (int) round($history->avg('price'));

        // Check 1: flash price barely below 30-day average
        $isPriceBarellyBelowAvg = $avgPrice30d > 0 && $flashPrice >= ($avgPrice30d * 0.95);

        // Check 2: price raised significantly in last 7 days
        $last7  = $history->filter(fn ($h) => $h->recorded_at->gte(now()->subDays(7)));
        $older  = $history->filter(fn ($h) => $h->recorded_at->lt(now()->subDays(7)));
        $isPriceRecentlyInflated = false;
        if ($last7->isNotEmpty() && $older->isNotEmpty()) {
            $avgLast7  = $last7->avg('price');
            $avgOlder  = $older->avg('price');
            $isPriceRecentlyInflated = $avgOlder > 0 && ($avgLast7 > $avgOlder * 1.10);
        }

        // Check 3: original_price appears inflated vs history
        $maxHistoricalPrice       = (int) $history->max('price');
        $isDiscountArtificial     = $maxHistoricalPrice > 0 && $maxHistoricalPrice < ($originalPrice * 0.90);

        $triggeredCount = (int) $isPriceBarellyBelowAvg + (int) $isPriceRecentlyInflated + (int) $isDiscountArtificial;
        $confidence     = $triggeredCount > 0 ? round($triggeredCount / 3, 2) : 0.0;

        $reasons = [];
        if ($isPriceBarellyBelowAvg) {
            $reasons[] = 'Flash price barely below 30-day average';
        }
        if ($isPriceRecentlyInflated) {
            $reasons[] = 'Price was raised significantly in last 7 days';
        }
        if ($isDiscountArtificial) {
            $reasons[] = 'Original price appears inflated vs history';
        }

        return [
            'is_suspect'                  => $triggeredCount > 0,
            'confidence'                  => $confidence,
            'reasons'                     => $reasons,
            'average_price_30d'           => $avgPrice30d,
            'average_price_30d_formatted' => number_format($avgPrice30d, 2),
            'price_history_count'         => $history->count(),
            'checks' => [
                'is_price_barely_below_avg'   => $isPriceBarellyBelowAvg,
                'is_price_recently_inflated'  => $isPriceRecentlyInflated,
                'discount_is_artificial'      => $isDiscountArtificial,
            ],
        ];
    }
}
