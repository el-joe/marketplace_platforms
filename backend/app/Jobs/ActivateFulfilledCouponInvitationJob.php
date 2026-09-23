<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Closes open coupon participation invitations past their deadline and
 * fulfils/activates the coupon when there are approved participants.
 * Logic lives in coupons:close-expired-participation-invitations.
 */
class ActivateFulfilledCouponInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Artisan::call('coupons:close-expired-participation-invitations');
    }
}
