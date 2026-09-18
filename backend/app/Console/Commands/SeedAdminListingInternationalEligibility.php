<?php

namespace App\Console\Commands;

use App\Models\AdminListing;
use App\Models\Country;
use App\Models\InternationalShippingEligibility;
use Illuminate\Console\Command;

/**
 * docs/plans/international_product_shipping.md Phase 5 / design decision #2.
 *
 * Uniform opt-in eligibility means Nawi's own catalog (admin_listings) needs
 * an explicit backfill of international_shipping_eligibility rows rather than
 * shipping everywhere by default with no row. This one-off command bulk-seeds
 * "ships everywhere active+launched" for every active admin listing.
 *
 * A listing never ships to its own origin country (that's domestic).
 * Existing rows (active or previously deactivated) are left untouched —
 * this only creates rows that don't already exist, it never reactivates a
 * row an admin has deliberately turned off.
 *
 * Do NOT run this automatically. Run manually once, after Phase 5 deploys:
 *   php artisan international-shipping:seed-admin-eligibility
 *   php artisan international-shipping:seed-admin-eligibility --dry-run
 */
class SeedAdminListingInternationalEligibility extends Command
{
    protected $signature = 'international-shipping:seed-admin-eligibility {--chunk=200} {--dry-run}';

    protected $description = 'Backfill international_shipping_eligibility rows for all active admin_listings against all active+launched countries';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $destinationCountries = Country::query()
            ->where('is_active', true)
            ->where('is_launched', true)
            ->get(['id']);

        if ($destinationCountries->isEmpty()) {
            $this->warn('No active+launched countries found — nothing to seed.');

            return self::SUCCESS;
        }

        $destinationCountryIds = $destinationCountries->pluck('id');
        $created = 0;
        $skipped = 0;

        AdminListing::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($listings) use ($destinationCountryIds, $dryRun, &$created, &$skipped) {
                foreach ($listings as $listing) {
                    $existingDestinationIds = InternationalShippingEligibility::query()
                        ->where('admin_listing_id', $listing->id)
                        ->pluck('destination_country_id')
                        ->all();

                    foreach ($destinationCountryIds as $destinationCountryId) {
                        // Never seed a listing as shipping to its own origin country.
                        if ($destinationCountryId === $listing->country_id) {
                            continue;
                        }

                        if (in_array($destinationCountryId, $existingDestinationIds, true)) {
                            $skipped++;
                            continue;
                        }

                        if (!$dryRun) {
                            InternationalShippingEligibility::create([
                                'admin_listing_id' => $listing->id,
                                'destination_country_id' => $destinationCountryId,
                                'is_active' => true,
                            ]);
                        }

                        $created++;
                    }
                }
            });

        $this->info(($dryRun ? '[dry run] Would create' : 'Created') . " {$created} eligibility row(s), skipped {$skipped} already existing.");

        return self::SUCCESS;
    }
}
