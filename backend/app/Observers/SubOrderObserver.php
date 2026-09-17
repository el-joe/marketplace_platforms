<?php

namespace App\Observers;

use App\Models\SubOrder;
use App\Models\WarrantyPurchase;
use App\Services\Customer\LoyaltyService;
use Illuminate\Support\Facades\DB;

class SubOrderObserver
{
    public function updating(SubOrder $subOrder): void
    {
        if (! $subOrder->isDirty('status')) {
            return;
        }

        $newStatus = $subOrder->status instanceof \BackedEnum
            ? $subOrder->status->value
            : $subOrder->status;

        if ($newStatus !== 'delivered') {
            return;
        }

        // ── Loyalty points earn ───────────────────────────────────────────────
        // Dispatched independently of the warranty transaction below, so a
        // warranty failure doesn't block point earning and vice versa.
        try {
            app(LoyaltyService::class)->earnForSubOrder($subOrder);
        } catch (\Throwable $e) {
            // Non-fatal: log and continue. Points can be manually adjusted
            // by admin if the earn fails — delivery must not be blocked.
            \Illuminate\Support\Facades\Log::error('LoyaltyService::earnForSubOrder failed', [
                'sub_order_id' => $subOrder->id,
                'error'        => $e->getMessage(),
            ]);
        }

        $warrantyPurchaseIds = $subOrder->items()
            ->whereNotNull('warranty_purchase_id')
            ->pluck('warranty_purchase_id');

        $warrantyPurchases = WarrantyPurchase::query()
            ->whereIn('id', $warrantyPurchaseIds)
            ->get();

        if ($warrantyPurchases->isEmpty()) {
            return;
        }

        // enhancement.md P-09 task 2 / D3: coverage starts the day the
        // brand/vendor warranty ends (delivered_at + vendors.warranty_months),
        // or at delivery if the vendor has none — not `today()`.
        $deliveredAt = now();
        $vendorWarrantyMonths = $subOrder->vendor?->warranty_months;
        $coverageStartsAt = $vendorWarrantyMonths
            ? $deliveredAt->copy()->addMonths((int) $vendorWarrantyMonths)
            : $deliveredAt->copy();

        DB::transaction(function () use ($warrantyPurchases, $coverageStartsAt): void {
            foreach ($warrantyPurchases as $warrantyPurchase) {
                $durationMonths = (int) ($warrantyPurchase->plan_snapshot['duration_months'] ?? 0);

                $warrantyPurchase->update([
                    'coverage_starts_at' => $coverageStartsAt->toDateString(),
                    'coverage_ends_at' => $coverageStartsAt->copy()->addMonths($durationMonths)->toDateString(),
                    'status' => 'active',
                ]);
            }
        });
    }

    public function updated(SubOrder $subOrder): void
    {
        if (! $subOrder->wasChanged('status')) {
            return;
        }

        $order = \App\Models\Order::find($subOrder->order_id);
        $order?->syncStatusFromSubOrders(changedByAdminId: null);
    }
}
