<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Services\Customer\BuyBoxRebuildService;
use Illuminate\Console\Command;

/**
 * enhancement.md P-19 task 2: full rebuild of the `product_country_buybox`
 * read model. Run once after the migration that creates the table (the
 * table starts empty — a migration cannot backfill a computed read model
 * safely inside itself, so this command is the documented operational
 * step), and periodically/on-demand afterward as a safety net alongside
 * the observer-driven incremental updates.
 */
class RebuildBuyBox extends Command
{
    protected $signature = 'buybox:rebuild {--country= : ISO/country id to rebuild; omit to rebuild every country}';

    protected $description = 'Rebuild the product_country_buybox read model (enhancement.md P-19)';

    public function handle(BuyBoxRebuildService $rebuilder): int
    {
        $countryOpt = $this->option('country');

        $countries = $countryOpt
            ? Country::query()->where('id', $countryOpt)->orWhere('iso_code_2', $countryOpt)->orWhere('iso_code_3', $countryOpt)->get()
            : Country::query()->get();

        if ($countries->isEmpty()) {
            $this->error('No matching country found.');
            return self::FAILURE;
        }

        foreach ($countries as $country) {
            $count = $rebuilder->rebuildCountry($country);
            $this->info("Rebuilt {$count} buy-box rows for {$country->name_en} ({$country->id}).");
        }

        return self::SUCCESS;
    }
}
