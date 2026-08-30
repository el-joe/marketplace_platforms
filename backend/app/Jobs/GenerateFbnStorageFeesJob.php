<?php

namespace App\Jobs;

use App\Models\FbnStorageFee;
use App\Models\WarehouseInventory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates monthly FBN storage fee records for all vendors with
 * active warehouse inventory in platform_fbn warehouses.
 *
 * Scheduled: 1st of each month (see routes/console.php).
 * Can also be dispatched manually from admin panel.
 */
class GenerateFbnStorageFeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private string $monthString)
    {
        // $monthString format: "Y-m" e.g. "2026-06"
    }

    public function handle(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->monthString)->startOfMonth()->toDateString();

        Log::info("[GenerateFbnStorageFeesJob] Generating storage fees for month: {$month}");

        // Get all warehouse_inventories in platform_fbn warehouses with stock
        $inventories = WarehouseInventory::query()
            ->select([
                'warehouse_inventories.id as inventory_id',
                'warehouse_inventories.quantity_on_hand',
                'vendor_listings.vendor_id',
                'warehouses.storage_rate_per_m3_price as rate_per_unit',
                'warehouses.storage_currency as currency',
            ])
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('warehouses.type', 'platform_fbn')
            ->where('warehouses.is_active', true)
            ->where('warehouse_inventories.quantity_on_hand', '>', 0)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($inventories as $inv) {
            $rateCents = (int) ($inv->rate_per_unit ?? 0);
            if ($rateCents <= 0) {
                $skipped++;
                continue;
            }

            if (! $inv->currency) {
                Log::warning("[GenerateFbnStorageFeesJob] Warehouse has no storage_currency, skipping inventory {$inv->inventory_id}");
                $skipped++;
                continue;
            }

            $totalCents = $inv->quantity_on_hand * $rateCents;

            try {
                FbnStorageFee::updateOrCreate(
                    [
                        'vendor_id' => $inv->vendor_id,
                        'warehouse_inventory_id' => $inv->inventory_id,
                        'month' => $month,
                    ],
                    [
                        'units_stored' => $inv->quantity_on_hand,
                        'rate_per_unit' => $rateCents,
                        'total_fee' => $totalCents,
                        'currency' => $inv->currency,
                        'status' => 'pending',
                    ]
                );
                $created++;
            } catch (\Throwable $e) {
                Log::error("[GenerateFbnStorageFeesJob] Failed for inventory {$inv->inventory_id}: " . $e->getMessage());
            }
        }

        Log::info("[GenerateFbnStorageFeesJob] Done. Created/updated: {$created}, skipped (no rate): {$skipped}");
    }
}
