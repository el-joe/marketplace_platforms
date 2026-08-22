<?php

namespace App\Services;

use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseVendorLimit;
use Illuminate\Validation\ValidationException;

class WarehouseVendorLimitService
{
    public function limitFor(string $warehouseId, string $vendorId): ?WarehouseVendorLimit
    {
        return WarehouseVendorLimit::where('warehouse_id', $warehouseId)
            ->where('vendor_id', $vendorId)
            ->first();
    }

    /**
     * The limit that actually governs a vendor in this warehouse: their explicit
     * per-vendor override if one exists, otherwise the warehouse's default
     * (platform-owned warehouses only). Returns null when neither is set.
     *
     * @return array{limit_type: string, max_quantity: ?int, max_capacity_m3: ?float}|null
     */
    public function resolveLimit(string $warehouseId, string $vendorId): ?array
    {
        $explicit = $this->limitFor($warehouseId, $vendorId);
        if ($explicit) {
            return [
                'limit_type' => $explicit->limit_type,
                'max_quantity' => $explicit->max_quantity,
                'max_capacity_m3' => $explicit->max_capacity_m3 !== null ? (float) $explicit->max_capacity_m3 : null,
            ];
        }

        $warehouse = Warehouse::find($warehouseId);
        if ($warehouse && $warehouse->owner_vendor_id === null && $warehouse->default_limit_type) {
            return [
                'limit_type' => $warehouse->default_limit_type,
                'max_quantity' => $warehouse->default_max_quantity,
                'max_capacity_m3' => $warehouse->default_max_capacity_m3 !== null ? (float) $warehouse->default_max_capacity_m3 : null,
            ];
        }

        return null;
    }

    /**
     * Sum of on-hand + already-inbound units this vendor holds in the warehouse.
     */
    public function currentQuantityUsage(string $warehouseId, string $vendorId): int
    {
        return (int) WarehouseInventory::query()
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->where('warehouse_inventories.warehouse_id', $warehouseId)
            ->where('vendor_listings.vendor_id', $vendorId)
            ->selectRaw('COALESCE(SUM(warehouse_inventories.quantity_on_hand + warehouse_inventories.quantity_inbound), 0) as total')
            ->value('total');
    }

    /**
     * Sum of volume (m3) occupied by on-hand + inbound units this vendor holds in the warehouse.
     */
    public function currentCapacityUsage(string $warehouseId, string $vendorId): float
    {
        $rows = WarehouseInventory::query()
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
            ->where('warehouse_inventories.warehouse_id', $warehouseId)
            ->where('vendor_listings.vendor_id', $vendorId)
            ->selectRaw('(warehouse_inventories.quantity_on_hand + warehouse_inventories.quantity_inbound) as units, product_variants.length_cm, product_variants.width_cm, product_variants.height_cm')
            ->get();

        return (float) $rows->sum(fn ($row) => $row->units * $this->unitVolumeM3($row->length_cm, $row->width_cm, $row->height_cm));
    }

    public function unitVolumeM3(?float $lengthCm, ?float $widthCm, ?float $heightCm): float
    {
        if (!$lengthCm || !$widthCm || !$heightCm) {
            return 0.0;
        }

        return ($lengthCm * $widthCm * $heightCm) / 1_000_000;
    }

    /**
     * Throws a ValidationException if adding $additionalQuantity units of $vendorListing
     * would push the vendor over their configured limit for this warehouse.
     */
    public function assertWithinLimit(string $warehouseId, VendorListing $vendorListing, int $additionalQuantity): void
    {
        $limit = $this->resolveLimit($warehouseId, $vendorListing->vendor_id);

        if (!$limit) {
            return;
        }

        if ($limit['limit_type'] === 'quantity') {
            $projected = $this->currentQuantityUsage($warehouseId, $vendorListing->vendor_id) + $additionalQuantity;

            if ($limit['max_quantity'] !== null && $projected > $limit['max_quantity']) {
                throw ValidationException::withMessages([
                    'quantity_requested' => ["This would exceed your storage limit of {$limit['max_quantity']} units in this warehouse (currently using " . ($projected - $additionalQuantity) . ')'],
                ]);
            }

            return;
        }

        $variant = $vendorListing->productVariant;
        $unitVolume = $this->unitVolumeM3($variant?->length_cm, $variant?->width_cm, $variant?->height_cm);
        $projectedVolume = $this->currentCapacityUsage($warehouseId, $vendorListing->vendor_id) + ($unitVolume * $additionalQuantity);

        if ($limit['max_capacity_m3'] !== null && $projectedVolume > $limit['max_capacity_m3']) {
            throw ValidationException::withMessages([
                'quantity_requested' => ['This would exceed your storage capacity limit of ' . $limit['max_capacity_m3'] . ' m³ in this warehouse.'],
            ]);
        }
    }
}
