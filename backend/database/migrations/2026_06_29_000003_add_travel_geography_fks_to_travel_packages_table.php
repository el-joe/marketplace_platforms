<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->foreignUuid('destination_travel_country_id')
                ->nullable()
                ->after('destination_city')
                ->constrained('travel_countries')
                ->nullOnDelete();

            $table->foreignUuid('destination_travel_city_id')
                ->nullable()
                ->after('destination_travel_country_id')
                ->constrained('travel_cities')
                ->nullOnDelete();
        });

        // ── Backfill: fuzzy-match existing free-text destination_country values ──
        // Case-insensitive match against travel_countries.name_en.
        // Rows that cannot be matched are logged to stderr so they can be
        // manually reviewed and updated before the follow-up migration that
        // will drop the old varchar columns.
        $packages = DB::table('travel_packages')
            ->whereNull('destination_travel_country_id')
            ->whereNotNull('destination_country')
            ->get(['id', 'destination_country', 'destination_city']);

        $unmatched = [];

        foreach ($packages as $package) {
            $country = DB::table('travel_countries')
                ->whereRaw('LOWER(name_en) = LOWER(?)', [$package->destination_country])
                ->first(['id', 'name_en']);

            if (! $country) {
                // Try a starts-with match for slightly fuzzy entries (e.g. "UAE" ≠ "United Arab Emirates")
                $country = DB::table('travel_countries')
                    ->whereRaw('LOWER(name_en) LIKE LOWER(?)', [ltrim($package->destination_country, '%') . '%'])
                    ->first(['id', 'name_en']);
            }

            if (! $country) {
                $unmatched[] = [
                    'package_id'        => $package->id,
                    'destination_country' => $package->destination_country,
                    'destination_city'  => $package->destination_city,
                ];
                continue;
            }

            $cityId = null;
            if ($package->destination_city) {
                $city = DB::table('travel_cities')
                    ->where('travel_country_id', $country->id)
                    ->whereRaw('LOWER(name_en) = LOWER(?)', [$package->destination_city])
                    ->first(['id']);

                if (! $city) {
                    $city = DB::table('travel_cities')
                        ->where('travel_country_id', $country->id)
                        ->whereRaw('LOWER(name_en) LIKE LOWER(?)', [$package->destination_city . '%'])
                        ->first(['id']);
                }

                $cityId = $city?->id;
            }

            DB::table('travel_packages')->where('id', $package->id)->update([
                'destination_travel_country_id' => $country->id,
                'destination_travel_city_id'    => $cityId,
            ]);
        }

        if (! empty($unmatched)) {
            fwrite(STDERR, "\n[TravelPackagesMigration] The following packages could not be matched to a travel_country row.\n");
            fwrite(STDERR, "Please resolve these manually before running the follow-up migration that drops destination_country/destination_city.\n\n");
            foreach ($unmatched as $row) {
                fwrite(STDERR, sprintf(
                    "  package_id=%s  destination_country='%s'  destination_city='%s'\n",
                    $row['package_id'],
                    $row['destination_country'],
                    $row['destination_city'] ?? '',
                ));
            }
            fwrite(STDERR, "\n");
        }
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropForeign(['destination_travel_country_id']);
            $table->dropForeign(['destination_travel_city_id']);
            $table->dropColumn(['destination_travel_country_id', 'destination_travel_city_id']);
        });
    }
};
