<?php

namespace App\Jobs;

use App\Services\Marketer\MarketerSaleSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily: credits the net of every delivered, return-window-cleared sub-order whose seller is a marketer to that
 * marketer's wallet (pending until ReleaseMarketerPendingCommissionJob). Idempotent: one settlement per sub-order.
 */
class SettleMarketerSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketerSaleSettlementService $service): void
    {
        $service->settleAllEligible();
    }
}
