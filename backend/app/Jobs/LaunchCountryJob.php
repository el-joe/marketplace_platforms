<?php

namespace App\Jobs;

use App\Models\Country;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs after a country is launched (is_launched: false → true).
 *
 * Inserts a product_countries row for every active product that
 * does not already have one for this country, making all active
 * products visible in the newly launched country by default.
 *
 * Processes in chunks of 500 to avoid memory exhaustion on large
 * product catalogues.
 */
class LaunchCountryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    public function __construct(public readonly string $countryId)
    {
    }

    public function handle(): void
    {
        $country = Country::findOrFail($this->countryId);

        if (!$country->is_launched) {
            Log::warning("LaunchCountryJob: country {$this->countryId} is not launched — aborting.");
            return;
        }

        Log::info("LaunchCountryJob: starting product_countries batch for {$country->name_en}");

        $inserted = 0;
        $now = now();

        // Stream active products in chunks; skip those that already have
        // a product_countries row for this country (deduplication).
        DB::table('products')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($products) use ($country, $now, &$inserted) {
                $productIds = $products->pluck('id')->all();

                // Find which products already have a row for this country
                $existing = DB::table('product_countries')
                    ->where('country_id', $country->id)
                    ->whereIn('product_id', $productIds)
                    ->pluck('product_id')
                    ->flip()           // flip for O(1) lookup
                    ->all();

                $rows = [];
                foreach ($productIds as $productId) {
                    if (!array_key_exists($productId, $existing)) {
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'product_id' => $productId,
                            'country_id' => $country->id,
                            'is_available' => true,
                            'updated_by_admin_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('product_countries')->insert($rows);
                    $inserted += count($rows);
                }
            });

        Log::info("LaunchCountryJob: finished — {$inserted} product_countries rows inserted for {$country->name_en}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("LaunchCountryJob failed for country {$this->countryId}: " . $e->getMessage());
    }
}
