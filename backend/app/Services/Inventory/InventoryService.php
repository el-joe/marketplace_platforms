<?php

namespace App\Services\Inventory;

use App\Events\ListingStockChanged;
use App\Models\AdminListing;
use App\Models\InventoryMovement;
use App\Models\OrderItemAllocation;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * enhancement.md P-13: the ONE place every stock mutation goes through.
 *
 * Every public method here:
 *  - runs inside a DB transaction (nested inside whatever the caller is
 *    already in, via a savepoint, if any);
 *  - takes a row lock (`lockForUpdate`) on every warehouse_inventories
 *    row it touches, so concurrent mutations against the same row are
 *    serialized instead of racing;
 *  - refuses to leave on_hand or reserved negative;
 *  - writes an `inventory_movements` row with a correctly-signed
 *    `quantity_delta`;
 *  - dispatches `ListingStockChanged` so listing.status stays in sync.
 *
 * No method here ever picks "the first row of a listing" — `reserve()`
 * is the only method that *chooses* a row (or rows) for a listing; every
 * other method is handed the exact row (or an allocation that points at
 * the exact row) by its caller.
 */
class InventoryService
{
    /**
     * Reserve `$qty` units of a listing's stock for `$referenceType`/`$referenceId`.
     * Picks warehouse rows by: the given preferred warehouse first (if any and
     * it has stock), then most-available-first, splitting across rows only
     * when one row cannot cover the whole quantity.
     *
     * @return array<int, array{warehouse_inventory_id:string, quantity:int}>
     */
    public function reserve(
        VendorListing|AdminListing $listing,
        int $qty,
        string $referenceType,
        ?string $referenceId,
        ?string $actorType = null,
        ?string $actorId = null,
        ?string $preferredWarehouseId = null,
        string $reason = 'Reservation',
    ): array {
        if ($qty <= 0) {
            throw new RuntimeException('Reservation quantity must be positive.');
        }

        return DB::transaction(function () use ($listing, $qty, $referenceType, $referenceId, $actorType, $actorId, $preferredWarehouseId, $reason) {
            $column = $listing instanceof VendorListing ? 'vendor_listing_id' : 'admin_listing_id';

            $query = WarehouseInventory::where($column, $listing->id)
                ->lockForUpdate();

            if ($preferredWarehouseId) {
                $query->orderByRaw('warehouse_id = ? DESC', [$preferredWarehouseId]);
            }

            $rows = $query->orderByRaw('(quantity_on_hand - quantity_reserved) DESC')->get();

            $remaining = $qty;
            $allocations = [];

            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $row->quantity_on_hand - $row->quantity_reserved;
                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $remaining);
                $this->mutate($row, reservedDelta: $take, reason: $reason, movementType: 'reservation', referenceType: $referenceType, referenceId: $referenceId, actorType: $actorType, actorId: $actorId, quantityDeltaSign: 1);

                $allocations[] = ['warehouse_inventory_id' => $row->id, 'quantity' => $take];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InsufficientStockException(
                    "Insufficient stock for listing {$listing->id}: requested {$qty}, short by {$remaining}."
                );
            }

            $this->fireStockChanged($listing);

            return $allocations;
        });
    }

    /**
     * Release previously reserved stock back to availability (order
     * cancelled / payment failed / never fulfilled). Accepts either
     * OrderItemAllocation models or raw ['warehouse_inventory_id','quantity']
     * arrays.
     */
    public function release(iterable $allocations, string $referenceType, ?string $referenceId, ?string $actorType = null, ?string $actorId = null, string $reason = 'Release'): void
    {
        DB::transaction(function () use ($allocations, $referenceType, $referenceId, $actorType, $actorId, $reason) {
            foreach ($allocations as $allocation) {
                [$warehouseInventoryId, $qty, $model] = $this->normalizeAllocation($allocation);

                if ($qty <= 0) {
                    continue;
                }

                $row = WarehouseInventory::where('id', $warehouseInventoryId)->lockForUpdate()->first();
                if (! $row) {
                    continue;
                }

                $releaseQty = min($qty, $row->quantity_reserved);
                if ($releaseQty <= 0) {
                    continue;
                }

                $this->mutate($row, reservedDelta: -$releaseQty, reason: $reason, movementType: 'release', referenceType: $referenceType, referenceId: $referenceId, actorType: $actorType, actorId: $actorId, quantityDeltaSign: -1);

                if ($model instanceof OrderItemAllocation) {
                    $model->update(['status' => 'released']);
                }

                $this->fireStockChangedForRow($row);
            }
        });
    }

    /**
     * Commit reserved stock on shipment: on_hand and reserved both drop
     * together, so reserved stock never leaks.
     */
    public function commit(iterable $allocations, string $referenceType, ?string $referenceId, ?string $actorType = null, ?string $actorId = null, string $reason = 'Shipped'): void
    {
        DB::transaction(function () use ($allocations, $referenceType, $referenceId, $actorType, $actorId, $reason) {
            foreach ($allocations as $allocation) {
                [$warehouseInventoryId, $qty, $model] = $this->normalizeAllocation($allocation);

                if ($qty <= 0) {
                    continue;
                }

                $row = WarehouseInventory::where('id', $warehouseInventoryId)->lockForUpdate()->first();
                if (! $row) {
                    continue;
                }

                if ($row->quantity_on_hand < $qty || $row->quantity_reserved < $qty) {
                    throw new InsufficientStockException(
                        "Cannot commit {$qty} units for warehouse_inventory {$row->id}: on_hand={$row->quantity_on_hand}, reserved={$row->quantity_reserved}."
                    );
                }

                $this->mutate($row, onHandDelta: -$qty, reservedDelta: -$qty, reason: $reason, movementType: 'outbound', referenceType: $referenceType, referenceId: $referenceId, actorType: $actorType, actorId: $actorId, quantityDeltaSign: -1);

                if ($model instanceof OrderItemAllocation) {
                    $model->update(['status' => 'committed']);
                }

                $this->fireStockChangedForRow($row);
            }
        });
    }

    /**
     * Put stock back on the shelf (return, inbound receipt). Does NOT
     * touch reserved — a restock is new sellable on_hand.
     */
    public function restock(string $warehouseInventoryId, int $qty, string $referenceType, ?string $referenceId, ?string $actorType = null, ?string $actorId = null, string $reason = 'Restock', ?OrderItemAllocation $allocation = null): void
    {
        if ($qty <= 0) {
            return;
        }

        DB::transaction(function () use ($warehouseInventoryId, $qty, $referenceType, $referenceId, $actorType, $actorId, $reason, $allocation) {
            $row = WarehouseInventory::where('id', $warehouseInventoryId)->lockForUpdate()->first();
            if (! $row) {
                throw new RuntimeException("warehouse_inventory {$warehouseInventoryId} not found.");
            }

            $this->mutate($row, onHandDelta: $qty, reason: $reason, movementType: 'inbound', referenceType: $referenceType, referenceId: $referenceId, actorType: $actorType, actorId: $actorId, quantityDeltaSign: 1);

            if ($allocation) {
                $allocation->update(['status' => 'returned']);
            }

            $this->fireStockChangedForRow($row);
        });
    }

    /** Manual on_hand adjustment (stock count correction, etc). */
    public function adjust(WarehouseInventory $row, int $delta, string $reason, ?string $actorType = null, ?string $actorId = null): void
    {
        if ($delta === 0) {
            return;
        }

        DB::transaction(function () use ($row, $delta, $reason, $actorType, $actorId) {
            $locked = WarehouseInventory::where('id', $row->id)->lockForUpdate()->first();

            $this->mutate($locked, onHandDelta: $delta, reason: $reason, movementType: 'adjustment', referenceType: 'adjustment', referenceId: null, actorType: $actorType, actorId: $actorId, quantityDeltaSign: $delta >= 0 ? 1 : -1);

            $this->fireStockChangedForRow($locked);
        });
    }

    /** Move on_hand units to damaged (out of sellable stock). */
    public function damage(WarehouseInventory $row, int $qty, string $reason = 'Damaged', ?string $actorType = null, ?string $actorId = null): void
    {
        if ($qty <= 0) {
            return;
        }

        DB::transaction(function () use ($row, $qty, $reason, $actorType, $actorId) {
            $locked = WarehouseInventory::where('id', $row->id)->lockForUpdate()->first();

            if ($locked->quantity_on_hand < $qty) {
                throw new InsufficientStockException("Cannot damage {$qty} units: only {$locked->quantity_on_hand} on hand.");
            }

            $locked->quantity_damaged += $qty;
            $this->mutate($locked, onHandDelta: -$qty, reason: $reason, movementType: 'damage', referenceType: 'adjustment', referenceId: null, actorType: $actorType, actorId: $actorId, quantityDeltaSign: -1, extraSave: true);

            $this->fireStockChangedForRow($locked);
        });
    }

    /** Move on_hand units from one warehouse row to another. */
    public function transfer(WarehouseInventory $from, WarehouseInventory $to, int $qty, string $reason = 'Transfer', ?string $actorType = null, ?string $actorId = null): void
    {
        if ($qty <= 0) {
            return;
        }

        DB::transaction(function () use ($from, $to, $qty, $reason, $actorType, $actorId) {
            // Lock in a stable order (by id) to avoid deadlocks between two
            // concurrent transfers touching the same pair of rows.
            $ids = [$from->id, $to->id];
            sort($ids);
            $locked = WarehouseInventory::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $fromRow = $locked[$from->id];
            $toRow = $locked[$to->id];

            if ($fromRow->quantity_on_hand < $qty) {
                throw new InsufficientStockException("Cannot transfer {$qty} units: only {$fromRow->quantity_on_hand} on hand at source.");
            }

            $this->mutate($fromRow, onHandDelta: -$qty, reason: $reason, movementType: 'transfer', referenceType: 'transfer', referenceId: $to->id, actorType: $actorType, actorId: $actorId, quantityDeltaSign: -1);
            $this->mutate($toRow, onHandDelta: $qty, reason: $reason, movementType: 'transfer', referenceType: 'transfer', referenceId: $from->id, actorType: $actorType, actorId: $actorId, quantityDeltaSign: 1);

            $this->fireStockChangedForRow($fromRow);
            $this->fireStockChangedForRow($toRow);
        });
    }

    /**
     * @return array{0:string,1:int,2:?OrderItemAllocation}
     */
    private function normalizeAllocation(mixed $allocation): array
    {
        if ($allocation instanceof OrderItemAllocation) {
            return [$allocation->warehouse_inventory_id, (int) $allocation->quantity, $allocation];
        }

        return [$allocation['warehouse_inventory_id'], (int) $allocation['quantity'], null];
    }

    private function mutate(
        WarehouseInventory $row,
        int $onHandDelta = 0,
        int $reservedDelta = 0,
        string $reason = '',
        string $movementType = 'adjustment',
        string $referenceType = 'adjustment',
        ?string $referenceId = null,
        ?string $actorType = null,
        ?string $actorId = null,
        int $quantityDeltaSign = 1,
        bool $extraSave = false,
    ): void {
        $newOnHand = $row->quantity_on_hand + $onHandDelta;
        $newReserved = $row->quantity_reserved + $reservedDelta;

        if ($newOnHand < 0) {
            throw new InsufficientStockException("on_hand would go negative for warehouse_inventory {$row->id}.");
        }

        if ($newReserved < 0) {
            throw new InsufficientStockException("reserved would go negative for warehouse_inventory {$row->id}.");
        }

        if ($newReserved > $newOnHand) {
            throw new InsufficientStockException("reserved ({$newReserved}) would exceed on_hand ({$newOnHand}) for warehouse_inventory {$row->id}.");
        }

        $row->quantity_on_hand = $newOnHand;
        $row->quantity_reserved = $newReserved;
        $row->save();

        $delta = $onHandDelta !== 0 ? $onHandDelta : $reservedDelta;

        InventoryMovement::create([
            'warehouse_inventory_id' => $row->id,
            'movement_type' => $movementType,
            'quantity_delta' => $delta,
            'quantity_after' => $newOnHand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by_user_id' => $actorType === 'admin' || $actorType === 'customer' || $actorType === 'vendor' ? $actorId : null,
            'actor_type' => $actorType,
        ]);
    }

    /**
     * enhancement.md P-14: available stock for a listing (vendor or admin),
     * used by campaign creation/monitoring to check against
     * min_stock_for_campaign without duplicating the warehouse_inventories
     * sum in every caller.
     */
    public function availableStock(VendorListing|AdminListing $listing): int
    {
        return (int) $listing->warehouseInventories()->sum('quantity_available');
    }

    private function fireStockChanged(VendorListing|AdminListing $listing): void
    {
        event(new ListingStockChanged(
            $listing instanceof VendorListing ? $listing->id : null,
            $listing instanceof AdminListing ? $listing->id : null,
        ));
    }

    private function fireStockChangedForRow(WarehouseInventory $row): void
    {
        event(new ListingStockChanged($row->vendor_listing_id, $row->admin_listing_id));
    }
}
