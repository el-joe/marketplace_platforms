<?php

namespace App\Jobs;

use App\Models\FbnStorageFee;
use App\Models\StorageFeeFreePeriodRule;
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

    /**
     * Volumetric weight divisor (cm^3 -> grams), i.e.
     * volumetric_weight_grams = (length_cm * width_cm * height_cm) / DIVISOR * 1000.
     *
     * The plan doc (section 5.1) leaves this "يُحدَّد مع العميل" (TBD, pending
     * client confirmation) between the two industry-standard values of 5000
     * and 6000. Hardcoded to 5000 here as the working default until the
     * client confirms — revisit once section 6 (or a follow-up) settles it.
     */
    private const VOLUMETRIC_DIVISOR = 5000;

    public function __construct(private string $monthString)
    {
        // $monthString format: "Y-m" e.g. "2026-06"
    }

    public function handle(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->monthString)->startOfMonth();
        $monthDate = $month->toDateString();

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

        // End of the billed month, used to measure days-in-storage consistently
        // regardless of when in the month the job actually runs.
        $billingCutoff = $month->copy()->endOfMonth();

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

            $storedSince = $inv->stored_since ? Carbon::parse($inv->stored_since) : null;
            if (! $storedSince) {
                Log::warning("[GenerateFbnStorageFeesJob] Inventory {$inv->inventory_id} has no stored-since date, skipping.");
                $skipped++;

                continue;
            }

            $daysInStorage = $storedSince->diffInDays($billingCutoff);

            // Actual declared weight (grams). Missing/zero is treated as 0 so
            // volumetric weight can still drive the chargeable weight.
            $actualWeightGrams = (int) ($inv->declared_weight_grams ?? 0);

            // Volumetric weight (grams): (L x W x H in cm) / divisor -> "volumetric kg",
            // converted to grams to compare against the actual weight on the
            // same unit before picking the chargeable (larger) one.
            $volumetricWeightGrams = 0;
            if ($inv->declared_length_cm && $inv->declared_width_cm && $inv->declared_height_cm) {
                $volumetricWeightKg = ((float) $inv->declared_length_cm * (float) $inv->declared_width_cm * (float) $inv->declared_height_cm)
                    / self::VOLUMETRIC_DIVISOR;
                $volumetricWeightGrams = (int) round($volumetricWeightKg * 1000);
            }

            $chargeableWeightGrams = max($actualWeightGrams, $volumetricWeightGrams);

            $freeDays = StorageFeeFreePeriodRule::freeDaysFor($chargeableWeightGrams);

            $withinFree = $daysInStorage <= $freeDays;
            if ($withinFree) {
                $freeOfCharge++;
            }

            // Fee = physical volume (m3) x rate per m3 x units on hand.
            $volumeM3 = ($inv->declared_length_cm && $inv->declared_width_cm && $inv->declared_height_cm)
                ? ((float) $inv->declared_length_cm * (float) $inv->declared_width_cm * (float) $inv->declared_height_cm) / 1_000_000
                : 0;

            $totalCents = $withinFree ? 0 : (int) round($volumeM3 * $rateCents * $inv->quantity_on_hand);

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
                        'total_fee' => $totalCents,
                        'currency' => $inv->currency,
                        'declared_weight_grams' => $actualWeightGrams,
                        'volumetric_weight_grams' => $volumetricWeightGrams,
                        'chargeable_weight_grams' => $chargeableWeightGrams,
                        'free_days_applied' => $freeDays,
                        'days_in_storage' => (int) $daysInStorage,
                        'within_free_period' => $withinFree,
                        'status' => 'pending',
                    ]
                );
                $created++;
            } catch (\Throwable $e) {
                Log::error("[GenerateFbnStorageFeesJob] Failed for inventory {$inv->inventory_id}: ".$e->getMessage());
            }
        }

        Log::info("[GenerateFbnStorageFeesJob] Done. Created/updated: {$created}, free of charge: {$freeOfCharge}, skipped (no rate/data): {$skipped}");
    }
}
