<?php

namespace App\Jobs;

use App\Enums\TravelBookingStatus;
use App\Models\TravelBooking;
use App\Services\Marketer\CampaignFeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Nightly: a confirmed booking whose trip is over (return date, or the departure date for one-day trips, has passed)
 * becomes `completed` with completed_at set. That is the moment ApproveMarketerConversionsJob may approve the
 * affiliate / broker commission earned on the booking.
 *
 * Also retries the campaign fee of travel agency campaigns that could not be collected from the agency wallet yet
 * (agencies have no payout statements to carry a pending fee).
 */
class CompleteTravelBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CampaignFeeService $fees): void
    {
        TravelBooking::where('status', TravelBookingStatus::Confirmed)
            ->whereHas('package', fn ($q) => $q->whereRaw('COALESCE(return_date, departure_date) < ?', [today()->toDateString()]))
            ->chunkById(200, function ($bookings) {
                foreach ($bookings as $booking) {
                    // saved() on the model keeps the marketer conversion in step (idempotent).
                    $booking->update(['status' => TravelBookingStatus::Completed, 'completed_at' => now()]);
                }
            });

        $fees->retryPendingAgencyFees();
    }
}
