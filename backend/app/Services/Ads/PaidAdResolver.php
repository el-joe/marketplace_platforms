<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Enums\PaidAdSlotTargetType;
use App\Models\PaidAdBooking;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves which ad creative/booking serves a given placement/page-block for a country,
 * with a short-lived cache (config('ads.resolver_cache_seconds')).
 */
class PaidAdResolver
{
    public function activeIndex(string $countryId): array
    {
        return Cache::remember(
            "paid_ads:active:{$countryId}",
            config('ads.resolver_cache_seconds'),
            fn () => $this->build($countryId),
        );
    }

    public function bust(string $countryId): void
    {
        Cache::forget("paid_ads:active:{$countryId}");
    }

    public function pick(array $candidates, ?string $sessionId, string $salt): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($sessionId) {
            $idx = crc32($sessionId.$salt) % count($candidates);

            return $candidates[$idx];
        }

        return $candidates[array_rand($candidates)];
    }

    private function build(string $countryId): array
    {
        $today = now()->toDateString();

        $bookings = PaidAdBooking::query()
            ->where('status', PaidAdBookingStatus::Active)
            ->where('country_id', $countryId)
            ->whereDate('booked_from', '<=', $today)
            ->whereDate('booked_until', '>=', $today)
            ->with([
                'currentCreative.files',
                'slot.placementDefinition',
                'vendor:id,store_name',
                'marketer:id,name',
            ])
            ->get();

        $placement = [];
        $pageBlock = [];

        foreach ($bookings as $booking) {
            $creative = $booking->currentCreative;
            $slot = $booking->slot;

            if (!$creative || !$slot || $creative->status !== PaidAdCreativeStatus::Approved) {
                continue;
            }

            $payload = PaidAdPresenter::present($booking);

            if ($slot->target_type === PaidAdSlotTargetType::Placement) {
                $code = $slot->placementDefinition?->code;

                if (!$code) {
                    continue;
                }

                $placement[$code][] = [
                    'slot_id' => $slot->id,
                    'category_id' => $slot->category_id,
                    'payload' => $payload,
                ];
            } elseif ($slot->target_type === PaidAdSlotTargetType::PageBlock) {
                if (!$slot->page_block_id) {
                    continue;
                }

                $position = (string) ($slot->item_position ?: 0);

                $pageBlock[$slot->page_block_id][$position][] = [
                    'slot_id' => $slot->id,
                    'fill_mode' => $slot->fill_mode,
                    'payload' => $payload,
                ];
            }
        }

        return [
            'placement' => $placement,
            'page_block' => $pageBlock,
        ];
    }
}
