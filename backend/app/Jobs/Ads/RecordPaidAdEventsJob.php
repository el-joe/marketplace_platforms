<?php

namespace App\Jobs\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Models\PaidAdBooking;
use App\Services\Ads\AdBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordPaidAdEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $bookingIds
     * @param  'impression'|'click'  $kind
     */
    public function __construct(
        public array $bookingIds,
        public string $kind,
    ) {
    }

    public function handle(AdBillingService $billingService): void
    {
        $counts = array_count_values($this->bookingIds);

        $bookings = PaidAdBooking::whereIn('id', array_keys($counts))
            ->where('status', PaidAdBookingStatus::Active->value)
            ->get();

        foreach ($bookings as $booking) {
            $n = $counts[$booking->id];
            $billingService->recordDelivery(
                $booking,
                $this->kind === 'impression' ? $n : 0,
                $this->kind === 'click' ? $n : 0,
            );
        }
    }
}
