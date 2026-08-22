<?php

namespace App\Services\Vendor;

use App\Jobs\CustomerShippedNotificationJob;
use App\Models\InventoryMovement;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
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

    public function cancel(SubOrder $subOrder, string $reason): SubOrder
    {
        if (! in_array($subOrder->status->value, self::CANCELLABLE_STATUSES)) {
            abort(422, "Order cannot be cancelled from status '{$subOrder->status->value}'.");
        }

        DB::transaction(function () use ($subOrder, $reason) {
            $previousStatus = $subOrder->status->value;

            $subOrder->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
            ]);

            OrderStatusHistory::create([
                'sub_order_id' => $subOrder->id,
                'from_status'  => $previousStatus,
                'to_status'    => 'cancelled',
                'reason'       => $reason,
            ]);

            // Release any previously reserved inventory
            if ($subOrder->warehouse_id) {
                $this->releaseInventory($subOrder);
            }
        });

        return $subOrder->fresh();
    }

    private function decrementInventory(SubOrder $subOrder): void
    {
        foreach ($subOrder->items as $item) {
            $listing = VendorListing::where('vendor_id', $subOrder->vendor_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if (! $listing) {
                continue;
            }

            $inventory = WarehouseInventory::where('vendor_listing_id', $listing->id)
                ->where('warehouse_id', $subOrder->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                continue;
            }

            $before = $inventory->quantity_on_hand;
            $inventory->decrement('quantity_on_hand', $item->quantity);

            InventoryMovement::create([
                'warehouse_inventory_id' => $inventory->id,
                'movement_type'          => 'outbound',
                'quantity_delta'         => -$item->quantity,
                'quantity_after'         => $before - $item->quantity,
                'reference_type'         => 'order',
                'reference_id'           => $subOrder->id,
                'reason'                 => "Shipped — sub_order {$subOrder->sub_order_number}",
                'created_by_user_id'     => null, // vendor-initiated; no users.id mapping
            ]);
        }
    }

    private function releaseInventory(SubOrder $subOrder): void
    {
        foreach ($subOrder->items as $item) {
            $listing = VendorListing::where('vendor_id', $subOrder->vendor_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if (! $listing) {
                continue;
            }

            $inventory = WarehouseInventory::where('vendor_listing_id', $listing->id)
                ->where('warehouse_id', $subOrder->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                continue;
            }

            $before = $inventory->quantity_on_hand;
            // Restore reservation that was held when order was placed
            $inventory->decrement('quantity_reserved', $item->quantity);

            InventoryMovement::create([
                'warehouse_inventory_id' => $inventory->id,
                'movement_type'          => 'release',
                'quantity_delta'         => $item->quantity,
                'quantity_after'         => $before,
                'reference_type'         => 'order',
                'reference_id'           => $subOrder->id,
                'reason'                 => "Cancelled — sub_order {$subOrder->sub_order_number}",
                'created_by_user_id'     => null,
            ]);
        }
    }
}
