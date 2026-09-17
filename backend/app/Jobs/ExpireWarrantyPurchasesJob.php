<?php

namespace App\Jobs;

use App\Models\WarrantyPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * enhancement.md P-09 task 3: daily job transitioning `active` -> `expired`
 * warranty purchases whose coverage has ended.
 */
class ExpireWarrantyPurchasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        WarrantyPurchase::where('status', 'active')
            ->whereNotNull('coverage_ends_at')
            ->whereDate('coverage_ends_at', '<', today())
            ->update(['status' => 'expired']);
    }
}
