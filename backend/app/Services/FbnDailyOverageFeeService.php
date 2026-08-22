<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\WarehouseType;
use App\Models\FbnDailyOverageFee;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Computes per-unit daily storage overage fees for platform_fbn warehouses
 * once a warehouse inventory's free storage period (warehouse.free_storage_days)
 * has expired. Runs daily via fbn:compute-daily-overage (see routes/console.php).
 */
class FbnDailyOverageFeeService
{
    public function __construct(
        private readonly ActivityLoggerService $activityLogger,
    ) {
    }

    public function computeOverageForDate(Carbon $date): void
    {
        $warehouses = Warehouse::query()
            ->where('type', WarehouseType::PlatformFbn->value)
            ->where('is_active', true)
            ->where('daily_fee_per_unit', '>', 0)
            ->get();

        foreach ($warehouses as $warehouse) {
            DB::transaction(function () use ($warehouse, $date) {
                $this->computeForWarehouse($warehouse, $date);
            });
        }

        $this->activityLogger->log(
            description: "Daily overage fees computed for {$date->toDateString()}",
            logName: 'fbn_fees',
            event: 'daily_overage_computed',
        );
    }

    private function computeForWarehouse(Warehouse $warehouse, Carbon $date): void
    {
        $inventories = WarehouseInventory::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_on_hand', '>', 0)
            ->with('vendorListing')
            ->get();

        foreach ($inventories as $inventory) {
            $receivedAt = $inventory->inventoryMovements()
                ->where('movement_type', InventoryMovementType::Inbound->value)
                ->orderBy('created_at')
                ->value('created_at');

            if ($receivedAt === null) {
                continue;
            }

            $receivedAt = Carbon::parse($receivedAt)->startOfDay();
            $freePeriodEndsAt = $receivedAt->copy()->addDays($warehouse->free_storage_days);

            if (!$date->gt($freePeriodEndsAt)) {
                continue;
            }

            $vendorId = $inventory->vendorListing?->vendor_id;
            if ($vendorId === null) {
                continue;
            }

            $totalFee = $inventory->quantity_on_hand * $warehouse->daily_fee_per_unit;

            try {
                FbnDailyOverageFee::firstOrCreate(
                    [
                        'warehouse_inventory_id' => $inventory->id,
                        'fee_date' => $date->toDateString(),
                    ],
                    [
                        'vendor_id' => $vendorId,
                        'warehouse_id' => $warehouse->id,
                        'received_at' => $receivedAt->toDateString(),
                        'free_period_ends_at' => $freePeriodEndsAt->toDateString(),
                        'units' => $inventory->quantity_on_hand,
                        'fee_per_unit' => $warehouse->daily_fee_per_unit,
                        'total_fee' => $totalFee,
                        'currency' => $warehouse->daily_fee_currency ?? $warehouse->storage_currency,
                        'status' => 'pending',
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("[FbnDailyOverageFeeService] Failed for inventory {$inventory->id}: " . $e->getMessage());
            }
        }
    }
}
