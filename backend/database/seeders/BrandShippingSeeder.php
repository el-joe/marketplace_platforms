<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandShippingSeeder extends Seeder
{
    public function run(): void
    {
        // ── Brands ─────────────────────────────────────────────────────────
        $brands = [
            ['name_en' => 'Apple', 'name_ar' => 'آبل', 'website_url' => 'https://apple.com'],
            ['name_en' => 'Samsung', 'name_ar' => 'سامسونج', 'website_url' => 'https://samsung.com'],
            ['name_en' => 'Huawei', 'name_ar' => 'هواوي', 'website_url' => 'https://huawei.com'],
            ['name_en' => 'Xiaomi', 'name_ar' => 'شاومي', 'website_url' => 'https://xiaomi.com'],
            ['name_en' => 'Sony', 'name_ar' => 'سوني', 'website_url' => 'https://sony.com'],
            ['name_en' => 'LG', 'name_ar' => 'إل جي', 'website_url' => 'https://lg.com'],
            ['name_en' => 'Philips', 'name_ar' => 'فيليبس', 'website_url' => 'https://philips.com'],
            ['name_en' => 'Dyson', 'name_ar' => 'دايسون', 'website_url' => 'https://dyson.com'],
            ['name_en' => 'Bosch', 'name_ar' => 'بوش', 'website_url' => 'https://bosch.com'],
            ['name_en' => 'Nike', 'name_ar' => 'نايك', 'website_url' => 'https://nike.com'],
            ['name_en' => 'Adidas', 'name_ar' => 'أديداس', 'website_url' => 'https://adidas.com'],
            ['name_en' => "Levi's", 'name_ar' => 'ليفايز', 'website_url' => 'https://levis.com'],
            ['name_en' => 'Zara', 'name_ar' => 'زارا', 'website_url' => 'https://zara.com'],
            ['name_en' => 'H&M', 'name_ar' => 'إتش آند إم', 'website_url' => 'https://hm.com'],
            ['name_en' => "L'Oréal", 'name_ar' => 'لوريال', 'website_url' => 'https://loreal.com'],
            ['name_en' => 'Nivea', 'name_ar' => 'نيفيا', 'website_url' => 'https://nivea.com'],
            ['name_en' => 'Dove', 'name_ar' => 'دوف', 'website_url' => 'https://dove.com'],
            ['name_en' => 'Tefal', 'name_ar' => 'تيفال', 'website_url' => 'https://tefal.com'],
            ['name_en' => 'IKEA', 'name_ar' => 'إيكيا', 'website_url' => 'https://ikea.com'],
            ['name_en' => 'Noon Brand', 'name_ar' => 'ماركة نون', 'website_url' => 'https://noon.com'],
        ];

        $brandIds = [];
        foreach ($brands as $b) {
            $existing = DB::table('brands')->where('name_en', $b['name_en'])->first();
            if ($existing) {
                $brandIds[$b['name_en']] = $existing->id;
                continue;
            }
            $id = Str::uuid()->toString();
            $brandIds[$b['name_en']] = $id;
            $slug = Str::slug($b['name_en']);
            // Make slug unique
            $slugCheck = DB::table('brands')->where('slug', $slug)->exists();
            if ($slugCheck)
                $slug = $slug . '-' . substr($id, 0, 6);
            DB::table('brands')->insert([
                'id' => $id,
                'name_en' => $b['name_en'],
                'name_ar' => $b['name_ar'],
                'slug' => $slug,
                'website_url' => $b['website_url'] ?? null,
                'is_verified' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        cache()->put('seeder_brand_ids', $brandIds, 600);

        // ── Shipping Carriers ───────────────────────────────────────────────
        $carriers = [
            ['name' => 'Aramex', 'code' => 'aramex', 'tracking_url_pattern' => 'https://www.aramex.com/track/{tracking_number}', 'supports_cod' => true, 'supports_returns' => true],
            ['name' => 'DHL', 'code' => 'dhl', 'tracking_url_pattern' => 'https://www.dhl.com/track?id={tracking_number}', 'supports_cod' => false, 'supports_returns' => true],
            ['name' => 'Bosta', 'code' => 'bosta', 'tracking_url_pattern' => 'https://app.bosta.co/tracking/{tracking_number}', 'supports_cod' => true, 'supports_returns' => true],
            ['name' => 'SMSA', 'code' => 'smsa', 'tracking_url_pattern' => 'https://www.smsaexpress.com/tracking?ID={tracking_number}', 'supports_cod' => true, 'supports_returns' => true],
            ['name' => 'Fetchr', 'code' => 'fetchr', 'tracking_url_pattern' => 'https://fetchr.us/track/{tracking_number}', 'supports_cod' => true, 'supports_returns' => true],
            ['name' => 'FedEx', 'code' => 'fedex', 'tracking_url_pattern' => 'https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber={tracking_number}', 'supports_cod' => false, 'supports_returns' => true],
        ];

        $carrierIds = [];
        foreach ($carriers as $c) {
            $existing = DB::table('shipping_carriers')->where('code', $c['code'])->first();
            if ($existing) {
                $carrierIds[$c['code']] = $existing->id;
                continue;
            }
            $id = Str::uuid()->toString();
            $carrierIds[$c['code']] = $id;
            DB::table('shipping_carriers')->insert(array_merge($c, [
                'id' => $id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        cache()->put('seeder_carrier_ids', $carrierIds, 600);

        // ── Shipping Methods ────────────────────────────────────────────────
        $methods = [
            ['code' => 'standard', 'name' => 'Standard Delivery', 'min_delivery_days' => 3, 'max_delivery_days' => 7],
            ['code' => 'express', 'name' => 'Express Delivery', 'min_delivery_days' => 1, 'max_delivery_days' => 2],
            ['code' => 'same_day', 'name' => 'Same-Day Delivery', 'min_delivery_days' => 0, 'max_delivery_days' => 1],
            ['code' => 'scheduled', 'name' => 'Scheduled Delivery', 'min_delivery_days' => 1, 'max_delivery_days' => 5],
            ['code' => 'pickup', 'name' => 'Click & Collect', 'min_delivery_days' => 1, 'max_delivery_days' => 3],
        ];

        $methodIds = [];
        foreach ($methods as $m) {
            $existing = DB::table('shipping_methods')->where('code', $m['code'])->first();
            if ($existing) {
                $methodIds[$m['code']] = $existing->id;
                continue;
            }
            $id = Str::uuid()->toString();
            $methodIds[$m['code']] = $id;
            DB::table('shipping_methods')->insert(array_merge($m, [
                'id' => $id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        cache()->put('seeder_method_ids', $methodIds, 600);

        // ── Shipping Zones (per launched country) ───────────────────────────
        $countryIds = cache('seeder_country_ids') ?? [];
        if (empty($countryIds)) {
            foreach (['SA', 'AE', 'EG', 'KW'] as $iso) {
                $r = DB::table('countries')->where('iso_code_2', $iso)->first();
                if ($r)
                    $countryIds[$iso] = $r->id;
            }
        }

        $zones = [
            'SA' => [
                ['name' => 'Riyadh Metro', 'description' => 'Riyadh and suburbs'],
                ['name' => 'Western Region', 'description' => 'Jeddah, Mecca, Medina'],
                ['name' => 'Eastern Province', 'description' => 'Dammam, Khobar, Jubail'],
                ['name' => 'Remote KSA', 'description' => 'All other Saudi cities'],
            ],
            'AE' => [
                ['name' => 'Dubai Metro', 'description' => 'Dubai city and surrounding areas'],
                ['name' => 'Abu Dhabi Metro', 'description' => 'Abu Dhabi and Al Ain'],
                ['name' => 'Northern Emirates', 'description' => 'Sharjah, Ajman, Fujairah, RAK'],
            ],
            'EG' => [
                ['name' => 'Greater Cairo', 'description' => 'Cairo, Giza, Heliopolis'],
                ['name' => 'Alexandria', 'description' => 'Alexandria and surroundings'],
                ['name' => 'Delta Region', 'description' => 'Mansoura, Tanta, Zagazig'],
                ['name' => 'Upper Egypt', 'description' => 'Luxor, Aswan, Asyut'],
                ['name' => 'Canal Zone', 'description' => 'Port Said, Suez, Ismailia'],
            ],
            'KW' => [
                ['name' => 'Kuwait Central', 'description' => 'Kuwait City, Salmiya, Hawalli'],
                ['name' => 'Kuwait Outskirts', 'description' => 'Ahmadi, Jahra'],
            ],
        ];

        $zoneIds = [];
        foreach ($zones as $iso => $zoneList) {
            $countryId = $countryIds[$iso] ?? null;
            if (!$countryId)
                continue;
            $zoneIds[$iso] = [];
            foreach ($zoneList as $z) {
                $existing = DB::table('shipping_zones')
                    ->where('country_id', $countryId)
                    ->where('name', $z['name'])
                    ->first();
                if ($existing) {
                    $zoneIds[$iso][$z['name']] = $existing->id;
                    continue;
                }
                $zid = Str::uuid()->toString();
                $zoneIds[$iso][$z['name']] = $zid;
                DB::table('shipping_zones')->insert([
                    'id' => $zid,
                    'country_id' => $countryId,
                    'name' => $z['name'],
                    'description' => $z['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        cache()->put('seeder_zone_ids', $zoneIds, 600);

        // ── Shipping Rates ──────────────────────────────────────────────────
        // For each destination zone × each method, create a rate
        // Prices in base currency cents (SAR/AED/EGP/KWD)
        $rateMatrix = [
            // [method_code, base_fee, rate_per_kg, free_threshold]
            ['standard', 1500, 500, 20000],  // 15 SAR, +5/kg, free above 200
            ['express', 2500, 1000, 50000],  // 25 SAR, +10/kg, free above 500
            ['same_day', 3500, 1500, null],   // 35 SAR, +15/kg, never free
            ['scheduled', 1000, 300, 15000],  // 10 SAR, +3/kg, free above 150
            ['pickup', 500, 0, null],   // 5 SAR flat
        ];

        $aramexId = $carrierIds['aramex'] ?? null;

        foreach ($zoneIds as $iso => $isoZones) {
            foreach ($isoZones as $zoneName => $zoneId) {
                foreach ($rateMatrix as [$methodCode, $baseFee, $ratePerKg, $freeThreshold]) {
                    $methodId = $methodIds[$methodCode] ?? null;
                    if (!$methodId)
                        continue;
                    $exists = DB::table('shipping_rates')
                        ->where('destination_zone_id', $zoneId)
                        ->where('shipping_method_id', $methodId)
                        ->exists();
                    if (!$exists) {
                        DB::table('shipping_rates')->insert([
                            'id' => Str::uuid(),
                            'origin_zone_id' => null,
                            'destination_zone_id' => $zoneId,
                            'shipping_method_id' => $methodId,
                            'carrier_id' => $aramexId,
                            'base_fee' => $baseFee,
                            'rate_per_kg' => $ratePerKg,
                            'min_weight_grams' => 0,
                            'volumetric_divisor' => 5000,
                            'free_shipping_threshold' => $freeThreshold,
                            'cod_extra_fee' => 200,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        // ── Country Shipping Settings ───────────────────────────────────────
        foreach ($countryIds as $iso => $countryId) {
            if (!in_array($iso, ['SA', 'AE', 'EG', 'KW']))
                continue;
            foreach ($methodIds as $code => $methodId) {
                $exists = DB::table('country_shipping_settings')
                    ->where('country_id', $countryId)
                    ->where('shipping_method_id', $methodId)
                    ->exists();
                if (!$exists) {
                    DB::table('country_shipping_settings')->insert([
                        'id' => Str::uuid(),
                        'country_id' => $countryId,
                        'shipping_method_id' => $methodId,
                        'is_active' => true,
                        'free_shipping_threshold' => 20000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
