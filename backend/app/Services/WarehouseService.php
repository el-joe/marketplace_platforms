<?php

namespace App\Services;

use App\Enums\InventoryTransferStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    // ─── Warehouse CRUD ──────────────────────────────────────────────────────

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse->refresh();
    }

    public function delete(Warehouse $warehouse): void
    {
        $warehouse->delete();
    }

    // ─── Inventory Adjustments ───────────────────────────────────────────────

    /**
     * Adjust the quantity_on_hand for a single inventory record.
     * Uses a database-level lock to prevent race conditions.
     * NEVER writes quantity_available (it is a GENERATED VIRTUAL column).
     */
    public function adjustInventory(
        string $warehouseInventoryId,
        int $delta,
        string $movementType,
        string $reason,
        string $createdByUserId,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): WarehouseInventory {
        return DB::transaction(function () use ($warehouseInventoryId, $delta, $movementType, $reason, $createdByUserId, $referenceType, $referenceId, ) {
            /** @var WarehouseInventory $inventory */
            $inventory = WarehouseInventory::lockForUpdate()->findOrFail($warehouseInventoryId);

            $newQty = $inventory->quantity_on_hand + $delta;

            if ($newQty < 0) {
                throw new \RuntimeException(__('common.exceptions.warehouse.negative_quantity_on_hand', ['qty' => $newQty]));
            }

            // Only write quantity_on_hand — quantity_available is VIRTUAL
            $inventory->update(['quantity_on_hand' => $newQty]);

            InventoryMovement::create([
                'warehouse_inventory_id' => $inventory->id,
                'movement_type' => $movementType,
                'quantity_delta' => $delta,
                'quantity_after' => $newQty,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'created_by_user_id' => $createdByUserId,
            ]);

            return $inventory->refresh();
        });
    }

    /**
     * Bulk-adjust multiple inventory records in a single transaction.
     * $items: array of ['warehouse_inventory_id', 'delta', 'reason']
     */
    public function bulkAdjust(array $items, string $movementType, string $createdByUserId): void
    {
        DB::transaction(function () use ($items, $movementType, $createdByUserId) {
            foreach ($items as $item) {
                $this->adjustInventory(
                    warehouseInventoryId: $item['warehouse_inventory_id'],
                    delta: (int) $item['delta'],
                    movementType: $movementType,
                    reason: $item['reason'] ?? 'Bulk adjustment',
                    createdByUserId: $createdByUserId,
                );
            }
        });
    }

    /**
     * Mark units as damaged (moves them from quantity_on_hand to quantity_damaged).
     */
    public function markDamaged(
        WarehouseInventory $inventory,
        int $quantity,
        string $reason,
        string $createdByUserId,
    ): WarehouseInventory {
        return DB::transaction(function () use ($inventory, $quantity, $reason, $createdByUserId) {
            /** @var WarehouseInventory $locked */
            $locked = WarehouseInventory::lockForUpdate()->findOrFail($inventory->id);

            if ($quantity > $locked->quantity_on_hand) {
                throw new \RuntimeException(__('common.exceptions.warehouse.damaged_exceeds_on_hand', ['qty' => $quantity, 'available' => $locked->quantity_on_hand]));
            }

            $newQty = $locked->quantity_on_hand - $quantity;

            $locked->update([
                'quantity_on_hand' => $newQty,
                'quantity_damaged' => $locked->quantity_damaged + $quantity,
            ]);

            InventoryMovement::create([
                'warehouse_inventory_id' => $locked->id,
                'movement_type' => \App\Enums\InventoryMovementType::Damage->value,
                'quantity_delta' => -$quantity,
                'quantity_after' => $newQty,
                'reason' => $reason,
                'created_by_user_id' => $createdByUserId,
            ]);

            return $locked->refresh();
        });
    }

    // ─── Stock Count ─────────────────────────────────────────────────────────

    /**
     * Set quantity_on_hand to an absolute count (stock-take reconciliation).
     */
    public function stockCount(
        WarehouseInventory $inventory,
        int $newCount,
        string $reason,
        string $createdByUserId,
    ): WarehouseInventory {
        return DB::transaction(function () use ($inventory, $newCount, $reason, $createdByUserId) {
            /** @var WarehouseInventory $locked */
            $locked = WarehouseInventory::lockForUpdate()->findOrFail($inventory->id);

            $delta = $newCount - $locked->quantity_on_hand;

            $locked->update([
                'quantity_on_hand' => $newCount,
                'last_counted_at' => now(),
            ]);

            InventoryMovement::create([
                'warehouse_inventory_id' => $locked->id,
                'movement_type' => \App\Enums\InventoryMovementType::Adjustment->value,
                'quantity_delta' => $delta,
                'quantity_after' => $newCount,
                'reason' => $reason,
                'created_by_user_id' => $createdByUserId,
            ]);

            return $locked->refresh();
        });
    }

    // ─── Transfers ───────────────────────────────────────────────────────────

    /**
     * Create a new pending transfer with its line items.
     * $data keys: source_warehouse_id, destination_warehouse_id, vendor_id, notes, expected_arrival_date
     * $items: array of ['vendor_listing_id', 'quantity_requested']
     */
    public function createTransfer(array $data, array $items, string $initiatedByUserId): InventoryTransfer
    {
        return DB::transaction(function () use ($data, $items, $initiatedByUserId) {
            $transfer = InventoryTransfer::create([
                'transfer_number' => $this->generateTransferNumber(),
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'status' => InventoryTransferStatus::Draft->value,
                'initiated_by_user_id' => $initiatedByUserId,
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                InventoryTransferItem::create([
                    'inventory_transfer_id' => $transfer->id,
                    'vendor_listing_id' => $item['vendor_listing_id'],
                    'quantity_requested' => (int) $item['quantity_requested'],
                    'quantity_received' => 0,
                    'damaged_quantity' => 0,
                ]);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Mark a transfer as shipped and deduct stock from source warehouse.
     */
    public function shipTransfer(InventoryTransfer $transfer, array $data, string $adminId): InventoryTransfer
    {
        if ($transfer->status !== InventoryTransferStatus::Draft) {
            throw new \RuntimeException(__('common.exceptions.warehouse.only_draft_can_be_shipped'));
        }

        return DB::transaction(function () use ($transfer, $data, $adminId) {
            $transfer->update([
                'status' => InventoryTransferStatus::InTransit->value,
                'shipped_at' => now(),
                'carrier' => $data['carrier'] ?? $transfer->carrier,
                'tracking_number' => $data['tracking_number'] ?? $transfer->tracking_number,
            ]);

            // Deduct from source warehouse
            foreach ($transfer->items as $item) {
                $sourceInventory = WarehouseInventory::where('warehouse_id', $transfer->source_warehouse_id)
                    ->where('vendor_listing_id', $item->vendor_listing_id)
                    ->first();

                if ($sourceInventory) {
                    $this->adjustInventory(
                        warehouseInventoryId: $sourceInventory->id,
                        delta: -$item->quantity_requested,
                        movementType: \App\Enums\InventoryMovementType::Transfer->value,
                        reason: "Shipped via transfer {$transfer->transfer_number}",
                        createdByUserId: $adminId,
                        referenceType: InventoryTransfer::class,
                        referenceId: $transfer->id,
                    );
                }
            }

            return $transfer->refresh();
        });
    }

    /**
     * Receive a transfer and add stock to the destination warehouse.
     * $receivedItems: array of ['inventory_transfer_item_id', 'quantity_received', 'damaged_quantity', 'condition_notes']
     */
    public function receiveTransfer(InventoryTransfer $transfer, array $receivedItems, string $adminId): InventoryTransfer
    {
        if ($transfer->status !== InventoryTransferStatus::InTransit) {
            throw new \RuntimeException(__('common.exceptions.warehouse.only_in_transit_can_be_received'));
        }

        return DB::transaction(function () use ($transfer, $receivedItems, $adminId) {
            foreach ($receivedItems as $receivedItem) {
                /** @var InventoryTransferItem $item */
                $item = InventoryTransferItem::findOrFail($receivedItem['inventory_transfer_item_id']);

                $qtyReceived = (int) $receivedItem['quantity_received'];
                $damaged = (int) ($receivedItem['damaged_quantity'] ?? 0);

                $item->update([
                    'quantity_received' => $qtyReceived,
                    'damaged_quantity' => $damaged,
                    'condition_notes' => $receivedItem['condition_notes'] ?? null,
                ]);

                $goodQty = max(0, $qtyReceived - $damaged);
                if ($goodQty > 0) {
                    $destInventory = WarehouseInventory::firstOrCreate(
                        [
                            'warehouse_id' => $transfer->destination_warehouse_id,
                            'vendor_listing_id' => $item->vendor_listing_id,
                        ],
                        [
                            'quantity_on_hand' => 0,
                            'quantity_reserved' => 0,
                            'quantity_inbound' => 0,
                            'quantity_damaged' => 0,
                        ]
                    );

                    $this->adjustInventory(
                        warehouseInventoryId: $destInventory->id,
                        delta: $goodQty,
                        movementType: \App\Enums\InventoryMovementType::Transfer->value,
                        reason: "Received via transfer {$transfer->transfer_number}",
                        createdByUserId: $adminId,
                        referenceType: InventoryTransfer::class,
                        referenceId: $transfer->id,
                    );
                }

                if ($damaged > 0) {
                    $destInventory ??= WarehouseInventory::firstOrCreate(
                        [
                            'warehouse_id' => $transfer->destination_warehouse_id,
                            'vendor_listing_id' => $item->vendor_listing_id,
                        ],
                        [
                            'quantity_on_hand' => 0,
                            'quantity_reserved' => 0,
                            'quantity_inbound' => 0,
                            'quantity_damaged' => 0,
                        ]
                    );

                    $destInventory->increment('quantity_damaged', $damaged);
                }
            }

            $transfer->update([
                'status' => InventoryTransferStatus::Received->value,
                'received_at' => now(),
            ]);

            return $transfer->refresh();
        });
    }

    /**
     * Cancel a transfer (only pending transfers can be cancelled).
     */
    public function cancelTransfer(InventoryTransfer $transfer): InventoryTransfer
    {
        if ($transfer->status !== InventoryTransferStatus::Draft) {
            throw new \RuntimeException(__('common.exceptions.warehouse.only_draft_can_be_cancelled'));
        }

        $transfer->update(['status' => InventoryTransferStatus::Cancelled->value]);

        return $transfer->refresh();
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function generateTransferNumber(): string
    {
        return 'TRF-' . strtoupper(substr(uniqid(), -8)) . '-' . date('Ymd');
    }
}
