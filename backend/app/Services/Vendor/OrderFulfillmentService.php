<?php

namespace App\Services\Vendor;

use App\Enums\CancelActor;
use App\Jobs\CustomerShippedNotificationJob;
use App\Models\InventoryMovement;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Services\OrderCancellationService;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    // Statuses from which a vendor can mark an order shipped
    const SHIPPABLE_STATUSES = ['processing', 'packed'];

    // Statuses from which cancellation is still permitted
    const CANCELLABLE_STATUSES = ['placed', 'confirmed', 'processing', 'packed'];

    public function ship(SubOrder $subOrder, array $data): SubOrder
    {
        if (! in_array($subOrder->status->value, self::SHIPPABLE_STATUSES)) {
            abort(422, "Order cannot be shipped from status '{$subOrder->status->value}'.");
        }

        DB::transaction(function () use ($subOrder, $data) {
            $subOrder->update([
                'status'           => 'shipped',
                'tracking_number'  => $data['tracking_number'],
                'carrier_id'       => $data['carrier_id'] ?? null,
                'shipped_at'       => now(),
                'sla_breached'     => $subOrder->sla_ship_deadline
                    ? now()->gt($subOrder->sla_ship_deadline)
                    : false,
            ]);

            OrderStatusHistory::create([
                'sub_order_id' => $subOrder->id,
                'from_status'  => $subOrder->getOriginal('status'),
                'to_status'    => 'shipped',
                'reason'       => 'Vendor marked as shipped',
                'metadata'     => [
                    'tracking_number' => $data['tracking_number'],
                    'carrier_id'      => $data['carrier_id'] ?? null,
                ],
            ]);

            if ($subOrder->warehouse_id) {
                $this->decrementInventory($subOrder);
            }
        });

        CustomerShippedNotificationJob::dispatch($subOrder->id);

        return $subOrder->fresh();
    }

    /**
     * enhancement.md P-06: delegates to OrderCancellationService so a
     * vendor cancel goes through the same stock-release/refund/coupon/
     * loyalty/warranty/marketer/ledger reversal as every other cancel
     * path, instead of this service's own inventory-only implementation.
     */
    public function cancel(SubOrder $subOrder, string $reason): SubOrder
    {
        if (! in_array($subOrder->status->value, self::CANCELLABLE_STATUSES)) {
            abort(422, "Order cannot be cancelled from status '{$subOrder->status->value}'.");
        }

        app(OrderCancellationService::class)->cancel($subOrder, CancelActor::Vendor, $reason);

        return $subOrder->fresh();
    }

    /**
     * enhancement.md P-13 task 1/3: commit through InventoryService using
     * the exact order_item_allocations rows this item was reserved from
     * (never re-derived by vendor_id + product_variant_id, which breaks
     * when a vendor lists the same variant in more than one listing/
     * country) — on_hand and reserved now drop together, so reserved
     * stock can never leak.
     */
    private function decrementInventory(SubOrder $subOrder): void
    {
        $inventoryService = app(\App\Services\Inventory\InventoryService::class);

        foreach ($subOrder->items as $item) {
            $allocations = $item->allocations()->where('status', 'reserved')->get();

            if ($allocations->isEmpty()) {
                continue;
            }

            $inventoryService->commit(
                $allocations,
                'sub_order',
                $subOrder->id,
                actorType: 'vendor',
                actorId: $subOrder->vendor_id,
                reason: "Shipped — sub_order {$subOrder->sub_order_number}",
            );
        }
    }
}
