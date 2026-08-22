<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Old hardcoded inclusion keys → bilingual labels, matching the static
     * options previously defined in travel-agency/packages/_form.blade.php.
     */
    private const LEGACY_OPTIONS = [
        'flights'   => ['en' => 'Flights', 'ar' => 'رحلات الطيران'],
        'hotel'     => ['en' => 'Hotel', 'ar' => 'الفندق'],
        'meals'     => ['en' => 'Meals', 'ar' => 'الوجبات'],
        'tours'     => ['en' => 'Tours', 'ar' => 'الجولات'],
        'visa'      => ['en' => 'Visa', 'ar' => 'التأشيرة'],
        'insurance' => ['en' => 'Insurance', 'ar' => 'التأمين'],
        'transfers' => ['en' => 'Transfers', 'ar' => 'المواصلات'],
    ];

    public function up(): void
    {
        $now = now();
        $idsByKey = [];

        foreach (array_values(self::LEGACY_OPTIONS) as $i => $labels) {
            $key = array_keys(self::LEGACY_OPTIONS)[$i];
            $id = (string) Str::uuid();
            $idsByKey[$key] = $id;

            DB::table('travel_inclusions')->insert([
                'id' => $id,
                'name_en' => $labels['en'],
                'name_ar' => $labels['ar'],
                'slug' => $key,
                'is_active' => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $packages = DB::table('travel_packages')->select('id', 'inclusions')->whereNotNull('inclusions')->get();

        foreach ($packages as $package) {
            $keys = json_decode($package->inclusions ?? '[]', true) ?: [];
            $rows = [];
            foreach ($keys as $key) {
                if (!isset($idsByKey[$key])) {
                    continue;
                }
                $rows[] = [
                    'travel_package_id' => $package->id,
                    'travel_inclusion_id' => $idsByKey[$key],
                    'created_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('travel_package_inclusions')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        DB::table('travel_package_inclusions')->truncate();
        DB::table('travel_inclusions')->whereIn('slug', array_keys(self::LEGACY_OPTIONS))->delete();
    }
};
