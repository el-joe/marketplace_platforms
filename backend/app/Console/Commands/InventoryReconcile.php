<?php

namespace App\Console\Commands;

use App\Models\WarehouseInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-13 task 8: rebuild each warehouse_inventories row's
 * `quantity_reserved` from the *open* order_item_allocations (status =
 * 'reserved') that point at it, and report any drift instead of trusting
 * the running counter, which several years of ad-hoc decrement/increment
 * call sites (fixed in this prompt) could have left wrong.
 *
 * Read-only by default; pass --fix to write the corrected value (each
 * correction is itself written through InventoryService::adjust-style
 * direct write plus a note, since there is no legitimate business event
 * to hang an InventoryMovement off of for a pure reconciliation).
 */
class InventoryReconcile extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Persist the corrected quantity_reserved values}';

    protected $description = 'Report (and optionally fix) drift between warehouse_inventories.quantity_reserved and open order_item_allocations';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $expected = DB::table('order_item_allocations')
            ->where('status', 'reserved')
            ->groupBy('warehouse_inventory_id')
            ->select('warehouse_inventory_id', DB::raw('SUM(quantity) as expected_reserved'))
            ->pluck('expected_reserved', 'warehouse_inventory_id');

        $rows = WarehouseInventory::all(['id', 'quantity_reserved']);
        $driftCount = 0;

        foreach ($rows as $row) {
            $expectedReserved = (int) ($expected[$row->id] ?? 0);

            if ($expectedReserved !== (int) $row->quantity_reserved) {
                $driftCount++;
                $this->line(sprintf(
                    'DRIFT warehouse_inventory=%s quantity_reserved=%d expected=%d',
                    $row->id,
                    $row->quantity_reserved,
                    $expectedReserved
                ));

                if ($fix) {
                    DB::table('warehouse_inventories')
                        ->where('id', $row->id)
                        ->update(['quantity_reserved' => $expectedReserved]);
                }
            }
        }

        if ($driftCount === 0) {
            $this->info('No drift found. quantity_reserved matches open order_item_allocations for every row.');
        } else {
            $this->warn("{$driftCount} row(s) with drift" . ($fix ? ' — corrected.' : ' — re-run with --fix to correct.'));
        }

        return self::SUCCESS;
    }
}
