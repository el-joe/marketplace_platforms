<?php

namespace App\Jobs;

use App\Services\MarketerInvitationRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * P7 safety net: vendor refunds of cancelled/rejected campaigns that could not be credited yet (frozen wallet) and
 * marketer fee-share clawbacks that the marketer wallet could not cover. Idempotent (the service skips done rows).
 */
class RetryInvitationFeeRefundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketerInvitationRefundService $service): void
    {
        $service->runSafetyNet();
    }
}
