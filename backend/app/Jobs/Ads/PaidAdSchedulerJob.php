<?php

namespace App\Jobs\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Enums\PaidAdPaymentStatus;
use App\Models\PaidAdBooking;
use App\Notifications\Ads\AdBookingPaymentReminderNotification;
use App\Services\Ads\AdBookingRecipients;
use App\Services\Ads\AdBookingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PaidAdSchedulerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AdBookingService $service): void
    {
        PaidAdBooking::with(['country', 'currentCreative'])
            ->whereIn('status', [
                PaidAdBookingStatus::Scheduled->value,
                PaidAdBookingStatus::Approved->value,
                PaidAdBookingStatus::Active->value,
                PaidAdBookingStatus::Paused->value,
                PaidAdBookingStatus::PendingReview->value,
            ])
            ->chunkById(200, function ($bookings) use ($service) {
                foreach ($bookings as $booking) {
                    try {
                        $this->evaluate($booking, $service);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function evaluate(PaidAdBooking $booking, AdBookingService $service): void
    {
        $today = Carbon::now($booking->country->timezone ?? 'UTC')->startOfDay();
        $bookedFrom = Carbon::parse($booking->booked_from)->startOfDay();
        $bookedUntil = Carbon::parse($booking->booked_until)->startOfDay();

        if ($booking->status === PaidAdBookingStatus::Scheduled && $today->gte($bookedFrom)) {
            $service->activateOrSchedule($booking);

            return;
        }

        if ($booking->status === PaidAdBookingStatus::Approved
            && in_array($booking->payment_status, [PaidAdPaymentStatus::Paid, PaidAdPaymentStatus::Reserved], true)
            && $booking->currentCreative
            && $booking->currentCreative->status === PaidAdCreativeStatus::Approved
        ) {
            $service->activateOrSchedule($booking);

            return;
        }

        if (in_array($booking->status, [PaidAdBookingStatus::Active, PaidAdBookingStatus::Paused], true)
            && $today->gt($bookedUntil)
        ) {
            $service->complete($booking, 'period_ended');

            return;
        }

        if ($booking->status === PaidAdBookingStatus::Approved
            && $booking->payment_status === PaidAdPaymentStatus::Unpaid
            && $booking->payment_due_at
        ) {
            if (Carbon::parse($booking->payment_due_at)->isPast()) {
                $service->expire($booking, 'payment_timeout');

                return;
            }

            $this->maybeSendPaymentReminder($booking);
        }

        if ($booking->status === PaidAdBookingStatus::PendingReview && $today->gte($bookedFrom)) {
            $service->expire($booking, 'review_timeout');
        }
    }

    private function maybeSendPaymentReminder(PaidAdBooking $booking): void
    {
        $dueAt = Carbon::parse($booking->payment_due_at);
        $hoursLeft = now()->diffInHours($dueAt, false);

        $window = match (true) {
            $hoursLeft <= 2 => '2h',
            $hoursLeft <= 24 => '24h',
            default => null,
        };

        if (! $window) {
            return;
        }

        $key = "ad_booking_payment_reminder:{$booking->id}:{$window}";
        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, true, now()->addDays(3));

        AdBookingRecipients::advertiserAdmins($booking)->each(
            fn ($admin) => $admin->notify(new AdBookingPaymentReminderNotification($booking, $window))
        );
    }
}
