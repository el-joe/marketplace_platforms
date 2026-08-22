<?php

// TODO: Dispatch this job from the OrderItem status-change handler when
// fulfillment_status transitions to 'delivered' and warranty_purchase exists for this item.

namespace App\Jobs;

use App\Models\WarrantyPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateWarrantyPurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public WarrantyPurchase $purchase) {}

    public function handle(): void
    {
        $purchase = $this->purchase->fresh();

        if ($purchase->status !== 'pending') {
            Log::warning('Skipping warranty purchase activation: not pending', [
                'warranty_purchase_id' => $purchase->id,
                'status' => $purchase->status,
            ]);

            return;
        }

        $purchase->coverage_starts_at = today();
        $purchase->coverage_ends_at = today()->addMonths($purchase->plan_snapshot['duration_months']);
        $purchase->status = 'active';
        $purchase->save();

        DB::table('order_items')
            ->where('id', $purchase->order_item_id)
            ->update(['warranty_purchase_id' => $purchase->id]);

        Log::info('Warranty purchase activated', [
            'warranty_purchase_id' => $purchase->id,
            'coverage_starts_at' => $purchase->coverage_starts_at,
            'coverage_ends_at' => $purchase->coverage_ends_at,
        ]);
    }
}
