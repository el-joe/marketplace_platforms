<?php

namespace App\Services;

use App\Enums\InventoryTransferStatus;
use App\Enums\WarehouseType;
use App\Models\Address;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Notifications\Vendor\LowStockAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorWarehouseService
{
    // ─── Register ─────────────────────────────────────────────────────────────

    public function registerWarehouse(array $data, Vendor $vendor): Warehouse
    {
        return DB::transaction(function () use ($data, $vendor) {
            $code = $this->generateUniqueCode('SW-', 'warehouses', 'code');

            $warehouse = Warehouse::create([
                'country_id'       => $vendor->country_id,
                'name'             => $data['name'],
                'code'             => $code,
                'type'             => WarehouseType::SellerOwned->value,
                'owner_vendor_id'  => $vendor->id,
                'total_capacity_m3' => $data['total_capacity_m3'] ?? null,
                'used_capacity_m3' => 0,
                'is_active'        => true,
            ]);

            $address = Address::create([
                'addressable_type' => Warehouse::class,
                'addressable_id'   => $warehouse->id,
                'country_id'       => $vendor->country_id,
                'city_id'          => $data['city_id'] ?? null,
                'area'             => $data['area'] ?? null,
                'street_address'   => $data['street_address'] ?? null,
                'building'         => $data['building'] ?? null,
                'address_type'     => 'shipping',
            ]);

            $warehouse->update(['address_id' => $address->id]);

            return $warehouse->fresh();
        });
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function updateWarehouse(Warehouse $warehouse, array $data, Vendor $vendor): Warehouse
    {
        $this->assertOwnership($warehouse, $vendor);

        DB::transaction(function () use ($warehouse, $data) {
            $warehouse->update(array_filter([
                'name'              => $data['name'] ?? null,
                'total_capacity_m3' => $data['total_capacity_m3'] ?? null,
            ], fn($v) => $v !== null));

            if ($warehouse->address) {
                $warehouse->address->update(array_filter([
                    'city_id'        => $data['city_id'] ?? null,
                    'area'           => $data['area'] ?? null,
                    'street_address' => $data['street_address'] ?? null,
                    'building'       => $data['building'] ?? null,
                ], fn($v) => $v !== null));
            }
        });

        return $warehouse->fresh(['address', 'country']);
    }

    // ─── Deactivate ───────────────────────────────────────────────────────────

    public function deactivate(Warehouse $warehouse, Vendor $vendor): void
    {
        $this->assertOwnership($warehouse, $vendor);

        $stockCount = WarehouseInventory::where('warehouse_id', $warehouse->id)
            ->where('quantity_on_hand', '>', 0)
            ->count();

        if ($stockCount > 0) {
            throw ValidationException::withMessages([
                'warehouse' => [__('common.exceptions.warehouse.cannot_deactivate_has_stock', ['count' => $stockCount])],
            ]);
        }

        $warehouse->update(['is_active' => false]);
    }

    // ─── Adjust Inventory ─────────────────────────────────────────────────────

    public function adjustInventory(
        WarehouseInventory $inventory,
        int $adjustment,
        string $reason,
        string $movementType,
        VendorAdmin $vendorAdmin
    ): WarehouseInventory {
        if ($inventory->warehouse->owner_vendor_id !== $vendorAdmin->vendor_id) {
            throw new AuthorizationException(__('common.exceptions.warehouse.not_authorised_inventory_adjustment'));
        }

        return DB::transaction(function () use ($inventory, $adjustment, $reason, $movementType, $vendorAdmin) {
            $locked = WarehouseInventory::lockForUpdate()->findOrFail($inventory->id);

            $newOnHand = $locked->quantity_on_hand + $adjustment;

            if ($newOnHand < 0) {
                throw ValidationException::withMessages([
                    'adjustment' => [__('common.exceptions.warehouse.stock_below_zero')],
                ]);
            }

            // Never write quantity_available — it is GENERATED VIRTUAL
            $locked->update(['quantity_on_hand' => $newOnHand]);

            InventoryMovement::create([
                'warehouse_inventory_id' => $locked->id,
                'movement_type'          => $movementType,
                'quantity_delta'         => $adjustment,
                'quantity_after'         => $newOnHand,
                'reference_type'         => 'adjustment',
                'reason'                 => $reason,
                'created_by_user_id'     => $vendorAdmin->id,
            ]);

            $fresh = $locked->fresh();

            if ($adjustment < 0 && $fresh->reorder_point !== null && $newOnHand <= $fresh->reorder_point) {
                Notification::send($vendorAdmin->vendor->vendorAdmins, new LowStockAlert($fresh));
            }

            return $fresh;
        });
    }

    // ─── Stock Count ──────────────────────────────────────────────────────────

    public function stockCount(
        WarehouseInventory $inventory,
        int $actualCount,
        VendorAdmin $vendorAdmin
    ): WarehouseInventory {
        $delta   = $actualCount - $inventory->quantity_on_hand;
        $updated = $this->adjustInventory($inventory, $delta, 'Physical stock count', 'adjustment', $vendorAdmin);
        $updated->update(['last_counted_at' => now()]);

        return $updated->fresh();
    }

    // ─── Create Transfer ──────────────────────────────────────────────────────

    public function createTransfer(array $data, Vendor $vendor, VendorAdmin $vendorAdmin): InventoryTransfer
    {
        $source = Warehouse::findOrFail($data['source_warehouse_id']);

        if ($source->owner_vendor_id !== $vendor->id) {
            throw new AuthorizationException(__('common.exceptions.warehouse.source_warehouse_not_owned'));
        }

        return DB::transaction(function () use ($data, $vendor, $vendorAdmin) {
            $number = $this->generateUniqueCode('TRF-', 'inventory_transfers', 'transfer_number', 8);

            $transfer = InventoryTransfer::create([
                'transfer_number'          => $number,
                'source_warehouse_id'      => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'vendor_id'                => $vendor->id,
                'status'                   => InventoryTransferStatus::Draft->value,
                'initiated_by_user_id'     => $vendorAdmin->id,
                'expected_arrival_date'    => $data['expected_arrival_date'] ?? null,
                'notes'                    => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                InventoryTransferItem::create([
                    'inventory_transfer_id' => $transfer->id,
                    'vendor_listing_id'     => $item['vendor_listing_id'],
                    'quantity_requested'    => $item['quantity'],
                ]);
            }

            return $transfer->load('items');
        });
    }

    // ─── Ship Transfer ────────────────────────────────────────────────────────

    public function shipTransfer(InventoryTransfer $transfer, array $data, Vendor $vendor): InventoryTransfer
    {
        if ($transfer->vendor_id !== $vendor->id) {
            throw new AuthorizationException(__('common.exceptions.warehouse.transfer_not_owned'));
        }

        if ($transfer->status !== InventoryTransferStatus::Draft) {
            throw ValidationException::withMessages(['status' => [__('common.exceptions.warehouse.transfer_not_draft')]]);
        }

        return DB::transaction(function () use ($transfer, $data) {
            foreach ($transfer->items as $item) {
                $sourceInv = WarehouseInventory::lockForUpdate()
                    ->where('warehouse_id', $transfer->source_warehouse_id)
                    ->where('vendor_listing_id', $item->vendor_listing_id)
                    ->firstOrFail();

                if ($sourceInv->quantity_on_hand < $item->quantity_requested) {
                    throw ValidationException::withMessages([
                        'items' => [__('common.exceptions.warehouse.insufficient_stock_items')],
                    ]);
                }

                $sourceInv->decrement('quantity_on_hand', $item->quantity_requested);
                $sourceInv->increment('quantity_reserved', $item->quantity_requested);

                InventoryMovement::create([
                    'warehouse_inventory_id' => $sourceInv->id,
                    'movement_type'          => 'transfer',
                    'quantity_delta'         => -$item->quantity_requested,
                    'quantity_after'         => $sourceInv->quantity_on_hand,
                    'reference_type'         => 'transfer',
                    'reference_id'           => $transfer->id,
                    'created_by_user_id'     => $transfer->initiated_by_user_id,
                    'reason'                 => 'Transfer ' . $transfer->transfer_number . ' shipped',
                ]);
            }

            $transfer->update([
                'status'           => InventoryTransferStatus::InTransit->value,
                'carrier'          => $data['carrier'] ?? null,
                'tracking_number'  => $data['tracking_number'] ?? null,
                'shipped_at'       => now(),
            ]);

            return $transfer->fresh();
        });
    }

    // ─── Cancel Transfer ──────────────────────────────────────────────────────

    public function cancelTransfer(InventoryTransfer $transfer, Vendor $vendor): InventoryTransfer
    {
        if ($transfer->vendor_id !== $vendor->id) {
            throw new AuthorizationException(__('common.exceptions.warehouse.transfer_not_owned'));
        }

        if (!in_array($transfer->status, [InventoryTransferStatus::Draft, InventoryTransferStatus::InTransit])) {
            throw ValidationException::withMessages(['status' => [__('common.exceptions.warehouse.transfer_cannot_be_cancelled')]]);
        }

        DB::transaction(function () use ($transfer) {
            if ($transfer->status === InventoryTransferStatus::InTransit) {
                foreach ($transfer->items as $item) {
                    $sourceInv = WarehouseInventory::lockForUpdate()
                        ->where('warehouse_id', $transfer->source_warehouse_id)
                        ->where('vendor_listing_id', $item->vendor_listing_id)
                        ->first();

                    if ($sourceInv) {
                        $sourceInv->increment('quantity_on_hand', $item->quantity_requested);
                        $sourceInv->decrement('quantity_reserved', $item->quantity_requested);

                        InventoryMovement::create([
                            'warehouse_inventory_id' => $sourceInv->id,
                            'movement_type'          => 'return',
                            'quantity_delta'         => $item->quantity_requested,
                            'quantity_after'         => $sourceInv->quantity_on_hand + $item->quantity_requested,
                            'reference_type'         => 'transfer',
                            'reference_id'           => $transfer->id,
                            'created_by_user_id'     => $transfer->initiated_by_user_id,
                            'reason'                 => 'Transfer ' . $transfer->transfer_number . ' cancelled',
                        ]);
                    }
                }
            }

            $transfer->update(['status' => InventoryTransferStatus::Cancelled->value]);
        });

        return $transfer->fresh();
    }

    // ─── Capacity Usage ───────────────────────────────────────────────────────

    public function getCapacityUsage(Warehouse $warehouse): array
    {
        $inventories = WarehouseInventory::where('warehouse_id', $warehouse->id)->get();

        $totalUnits     = $inventories->sum('quantity_on_hand');
        $totalSkus      = $inventories->count();
        $damagedUnits   = $inventories->sum('quantity_damaged');
        $inboundUnits   = $inventories->sum('quantity_inbound');
        $outOfStock     = $inventories->filter(fn($i) => $i->quantity_available <= 0)->count();
        $lowStock       = $inventories->filter(
            fn($i) => $i->reorder_point !== null && $i->quantity_on_hand <= $i->reorder_point
        )->count();

        $capacityPct = ($warehouse->total_capacity_m3 && $warehouse->total_capacity_m3 > 0)
            ? round(($warehouse->used_capacity_m3 / $warehouse->total_capacity_m3) * 100, 1)
            : null;

        return [
            'total_skus'         => $totalSkus,
            'total_units'        => $totalUnits,
            'low_stock_count'    => $lowStock,
            'out_of_stock_count' => $outOfStock,
            'capacity_used_pct'  => $capacityPct,
            'damaged_units'      => $damagedUnits,
            'inbound_units'      => $inboundUnits,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function assertOwnership(Warehouse $warehouse, Vendor $vendor): void
    {
        if ($warehouse->owner_vendor_id !== $vendor->id) {
            throw new AuthorizationException(__('common.exceptions.warehouse.warehouse_not_owned'));
        }
    }

    private function generateUniqueCode(string $prefix, string $table, string $column, int $length = 6): string
    {
        do {
            $code = $prefix . strtoupper(Str::random($length));
        } while (DB::table($table)->where($column, $code)->exists());

        return $code;
    }
}
