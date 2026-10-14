<?php

namespace App\Jobs;

use App\Services\AffiliateFeeSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hourly safety net for affiliate marketing fees: settles closed campaigns (finished, cancelled, commission budget
 * exhausted) whose accrual rows were not settled by the immediate triggers (late approvals/refunds) and retries the
 * vendor wallet for settlements still waiting for the weekly statement.
 */
class AffiliateFeeSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AffiliateFeeSettlementService $service): void
    {
        $service->runSafetyNet();
    }
}
