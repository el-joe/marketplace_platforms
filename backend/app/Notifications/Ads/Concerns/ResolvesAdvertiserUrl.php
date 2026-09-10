<?php

namespace App\Notifications\Ads\Concerns;

use App\Enums\PaidAdAdvertiserType;
use App\Models\PaidAdBooking;

trait ResolvesAdvertiserUrl
{
    private function advertiserUrl(PaidAdBooking $booking): ?string
    {
        return match ($booking->advertiser_type) {
            PaidAdAdvertiserType::Vendor => route('partner.ad-bookings.show', $booking->id),
            PaidAdAdvertiserType::Marketer => route('marketer.promote.bookings.show', $booking->id),
            default => null,
        };
    }
}
