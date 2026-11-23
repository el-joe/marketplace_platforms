<?php

namespace App\Jobs;

use App\Enums\FbnStorageFeeStatus;
use App\Models\FbnStorageFee;
use App\Models\WarehouseInventory;
use App\Services\Fbn\StorageFeeCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates monthly FBN storage fee records for all vendors with
 * active warehouse inventory in platform_fbn warehouses.
 *
 * Scheduled: 1st of each month (see routes/console.php).
 * Can also be dispatched manually from admin panel.
 *
 * Client feature request doc, section 5 ("رسوم التخزين الحقيقية حسب الوزن"):
 * fees are billed on the CHARGEABLE weight (max of actual declared weight
 * vs. volumetric weight), not flatly per unit on hand, and only after a
 * configurable free-storage period (StorageFeeFreePeriodRule) has elapsed
 * since the inventory entered the warehouse.
 */
class GenerateFbnStorageFeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    private const STATUS_TTL_SECONDS = 3600;

    public function __construct(private string $monthString)
    {
        // $monthString format: "Y-m" e.g. "2026-06"
    }

    /**
     * Cache key the admin panel polls for this month's generation progress.
     */
    public static function statusCacheKey(string $monthString): string
    {
        return "fbn_storage_fees_generation_status:{$monthString}";
    }

    public function handle(StorageFeeCalculator $calculator): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->monthString)->startOfMonth();
        $monthDate = $month->toDateString();
        $statusKey = self::statusCacheKey($this->monthString);

        Cache::put($statusKey, ['state' => 'running', 'created' => 0, 'free' => 0, 'skipped' => 0, 'locked' => 0], self::STATUS_TTL_SECONDS);

        Log::info("[GenerateFbnStorageFeesJob] Generating storage fees for month: {$monthDate}");

        // Get all warehouse_inventories in platform_fbn warehouses with stock
        $inventories = WarehouseInventory::query()
            ->select([
                'warehouse_inventories.id as inventory_id',
                'warehouse_inventories.quantity_on_hand',
                DB::raw('COALESCE(warehouse_inventories.first_stocked_at, warehouse_inventories.created_at) as stored_since'),
                'vendor_listings.vendor_id',
                'vendor_listings.declared_weight_grams',
                'vendor_listings.declared_length_cm',
                'vendor_listings.declared_width_cm',
                'vendor_listings.declared_height_cm',
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
        $freeOfCharge = 0;
        $locked = 0;

        // Fees already invoiced or paid for this month are finance records:
        // a re-run must never re-price them or push them back to pending.
        $lockedInventoryIds = FbnStorageFee::query()
            ->whereDate('month', $monthDate)
            ->where('status', '!=', FbnStorageFeeStatus::Pending)
            ->pluck('warehouse_inventory_id')
            ->flip();

        // End of the billed month, used to measure days-in-storage consistently
        // regardless of when in the month the job actually runs.
        $billingCutoff = $month->copy()->endOfMonth();

        foreach ($inventories as $inv) {
            if ($lockedInventoryIds->has($inv->inventory_id)) {
                $locked++;

                continue;
            }

            $rateCents = round((float) ($inv->rate_per_unit ?? 0), 4);
            if ($rateCents <= 0) {
                $skipped++;

                continue;
            }

            if (! $inv->currency) {
                Log::warning("[GenerateFbnStorageFeesJob] Warehouse has no storage_currency, skipping inventory {$inv->inventory_id}");
                $skipped++;

                continue;
            }

            $storedSince = $inv->stored_since ? Carbon::parse($inv->stored_since) : null;
            if (! $storedSince) {
                Log::warning("[GenerateFbnStorageFeesJob] Inventory {$inv->inventory_id} has no stored-since date, skipping.");
                $skipped++;

                continue;
            }

            // Cast to int immediately: $billingCutoff carries microseconds
            // (endOfMonth() sets 23:59:59.999999) while $storedSince read back
            // from the DB does not, so the raw float diff can overshoot a
            // whole-day boundary by a fraction of a second and wrongly flip
            // an inventory exactly at its free-day limit into "paid".
            $daysInStorage = (int) $storedSince->diffInDays($billingCutoff);

            // Actual declared weight (grams). Missing/zero is treated as 0 so
            // volumetric weight can still drive the chargeable weight.
            $actualWeightGrams = (int) ($inv->declared_weight_grams ?? 0);

            $volumetricWeightGrams = $calculator->volumetricWeightGrams(
                (float) $inv->declared_length_cm,
                (float) $inv->declared_width_cm,
                (float) $inv->declared_height_cm,
            );

            $chargeableWeightGrams = max($actualWeightGrams, $volumetricWeightGrams);

            $charge = $calculator->charge(
                $chargeableWeightGrams,
                $storedSince,
                $daysInStorage,
                $calculator->volumeM3((float) $inv->declared_length_cm, (float) $inv->declared_width_cm, (float) $inv->declared_height_cm),
                (float) $rateCents,
                (int) $inv->quantity_on_hand,
            );

            if ($charge['within_free_period']) {
                $freeOfCharge++;
            }

            try {
                FbnStorageFee::updateOrCreate(
                    [
                        'vendor_id' => $inv->vendor_id,
                        'warehouse_inventory_id' => $inv->inventory_id,
                        'month' => $monthDate,
                    ],
                    [
                        'units_stored' => $inv->quantity_on_hand,
                        'rate_per_unit' => $rateCents,
                        'total_fee' => $charge['total_fee'],
                        'currency' => $inv->currency,
                        'declared_weight_grams' => $actualWeightGrams,
                        'volumetric_weight_grams' => $volumetricWeightGrams,
                        'chargeable_weight_grams' => $chargeableWeightGrams,
                        'free_days_applied' => $charge['free_days_applied'],
                        'days_in_storage' => $daysInStorage,
                        'stored_since' => $storedSince->toDateString(),
                        'within_free_period' => $charge['within_free_period'],
                        'status' => FbnStorageFeeStatus::Pending,
                    ]
                );
                $created++;
            } catch (Throwable $e) {
                Log::error("[GenerateFbnStorageFeesJob] Failed for inventory {$inv->inventory_id}: ".$e->getMessage());
            }
        }

        Log::info("[GenerateFbnStorageFeesJob] Done. Created/updated: {$created}, free of charge: {$freeOfCharge}, skipped (no rate/data): {$skipped}, kept (invoiced/paid): {$locked}");

        Cache::put($statusKey, [
            'state' => 'done',
            'created' => $created,
            'free' => $freeOfCharge,
            'skipped' => $skipped,
            'locked' => $locked,
        ], self::STATUS_TTL_SECONDS);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[GenerateFbnStorageFeesJob] Failed: '.$exception?->getMessage());

        Cache::put(self::statusCacheKey($this->monthString), [
            'state' => 'failed',
            'created' => 0,
            'free' => 0,
            'skipped' => 0,
            'locked' => 0,
        ], self::STATUS_TTL_SECONDS);
    }
}
