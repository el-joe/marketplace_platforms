<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $countryIds = cache('seeder_country_ids') ?? [];
        if (empty($countryIds)) {
            foreach (['SA', 'AE', 'EG', 'KW'] as $iso) {
                $r = DB::table('countries')->where('iso_code_2', $iso)->first();
                if ($r)
                    $countryIds[$iso] = $r->id;
            }
        }

        // ── Vendors ─────────────────────────────────────────────────────────
        $vendors = [
            [
                'name' => 'Ahmed Al-Mansouri',
                'email' => 'vendor1@noon.com',
                'store_name' => 'TechHub Arabia',
                'store_slug' => 'techhub-arabia',
                'store_description' => 'Your one-stop-shop for the latest electronics and gadgets.',
                'business_name' => 'TechHub Trading LLC',
                'business_type' => 'llc',
                'contact_email' => 'contact@techhub.ae',
                'contact_phone' => '+971501234001',
                'iso' => 'AE',
            ],
            [
                'name' => 'Fatima Al-Rashidi',
                'email' => 'vendor2@noon.com',
                'store_name' => 'Fashion Palace',
                'store_slug' => 'fashion-palace',
                'store_description' => 'Premium fashion brands delivered to your door.',
                'business_name' => 'Fashion Palace Co.',
                'business_type' => 'corp',
                'contact_email' => 'info@fashionpalace.sa',
                'contact_phone' => '+966501234001',
                'iso' => 'SA',
            ],
            [
                'name' => 'Omar Hassan',
                'email' => 'vendor3@noon.com',
                'store_name' => 'Home Essentials',
                'store_slug' => 'home-essentials',
                'store_description' => 'Quality home goods and kitchen accessories.',
                'business_name' => 'Home Essentials Egypt',
                'business_type' => 'sole_prop',
                'contact_email' => 'omar@homeessentials.eg',
                'contact_phone' => '+201012345678',
                'iso' => 'EG',
            ],
            [
                'name' => 'Sara Al-Kuwaiti',
                'email' => 'vendor4@noon.com',
                'store_name' => 'Beauty Corner',
                'store_slug' => 'beauty-corner',
                'store_description' => 'Authentic beauty and skincare products.',
                'business_name' => 'Beauty Corner Trading',
                'business_type' => 'llc',
                'contact_email' => 'beauty@corner.kw',
                'contact_phone' => '+965501234001',
                'iso' => 'KW',
            ],
            [
                'name' => 'Khalid Al-Farsi',
                'email' => 'vendor5@noon.com',
                'store_name' => 'Sports Zone',
                'store_slug' => 'sports-zone',
                'store_description' => 'Everything you need for an active lifestyle.',
                'business_name' => 'Sports Zone Arabia',
                'business_type' => 'corp',
                'contact_email' => 'info@sportszone.sa',
                'contact_phone' => '+966502234001',
                'iso' => 'SA',
            ],
            [
                'name' => 'Layla Ibrahim',
                'email' => 'vendor6@noon.com',
                'store_name' => 'Kids World',
                'store_slug' => 'kids-world',
                'store_description' => 'Toys, games and everything for the little ones.',
                'business_name' => 'Kids World FZCO',
                'business_type' => 'llc',
                'contact_email' => 'layla@kidsworld.ae',
                'contact_phone' => '+971502234001',
                'iso' => 'AE',
            ],
            [
                'name' => 'Mohamed Abdelaziz',
                'email' => 'vendor7@noon.com',
                'store_name' => 'Gadget Galaxy',
                'store_slug' => 'gadget-galaxy',
                'store_description' => 'All the latest smartphones, tablets and accessories.',
                'business_name' => 'Gadget Galaxy Trading',
                'business_type' => 'llc',
                'contact_email' => 'info@gadgetgalaxy.sa',
                'contact_phone' => '+966503234001',
                'iso' => 'SA',
            ],
            [
                'name' => 'Nour Al-Sayed',
                'email' => 'vendor8@noon.com',
                'store_name' => 'Organic Bazaar',
                'store_slug' => 'organic-bazaar',
                'store_description' => 'Fresh organic and natural food products.',
                'business_name' => 'Organic Bazaar Egypt',
                'business_type' => 'sole_prop',
                'contact_email' => 'nour@organicbazaar.eg',
                'contact_phone' => '+201112345678',
                'iso' => 'EG',
            ],
        ];

        $vendorIds = [];
        foreach ($vendors as $v) {
            $iso = $v['iso'];
            $countryId = $countryIds[$iso] ?? null;

            $existing = DB::table('vendors')->where('email', $v['email'])->first();
            if ($existing) {
                $vendorIds[$v['store_slug']] = $existing->id;
                continue;
            }

            $id = Str::uuid()->toString();
            $vendorIds[$v['store_slug']] = $id;

            DB::table('vendors')->insert([
                'id' => $id,
                'name' => $v['name'],
                'email' => $v['email'],
                'email_verified_at' => now(),
                'phone' => $v['contact_phone'],
                'phone_verified_at' => now(),
                'password' => Hash::make('Vendor@123456'),
                'store_name' => $v['store_name'],
                'store_slug' => $v['store_slug'],
                'store_description' => $v['store_description'],
                'business_name' => $v['business_name'],
                'business_type' => $v['business_type'],
                'contact_email' => $v['contact_email'],
                'contact_phone' => $v['contact_phone'],
                'country_id' => $countryId,
                'payout_schedule' => 'monthly',
                'store_rating_avg' => number_format(rand(35, 50) / 10, 2),
                'store_rating_count' => rand(50, 500),
                'total_sales' => rand(10000, 500000) / 100,
                'total_orders' => rand(100, 5000),
                'return_rate_pct' => number_format(rand(1, 8) / 100, 2),
                'cancellation_rate_pct' => number_format(rand(1, 5) / 100, 2),
                'sla_compliance_pct' => number_format(rand(85, 99) / 100, 2),
                'strikes_count' => 0,
                'status' => 'active',
                'approved_at' => true,
                'created_at' => now()->subDays(rand(30, 365)),
                'updated_at' => now(),
            ]);
        }
        cache()->put('seeder_vendor_ids', $vendorIds, 600);

        // ── Customers ───────────────────────────────────────────────────────
        $customers = [
            ['name' => 'Ali Al-Zahrani', 'email' => 'ali@customer.com', 'phone' => '+966511111111', 'iso' => 'SA'],
            ['name' => 'Maha Al-Dosari', 'email' => 'maha@customer.com', 'phone' => '+966522222222', 'iso' => 'SA'],
            ['name' => 'Rami Hassan', 'email' => 'rami@customer.com', 'phone' => '+971511111111', 'iso' => 'AE'],
            ['name' => 'Noura Khalid', 'email' => 'noura@customer.com', 'phone' => '+971522222222', 'iso' => 'AE'],
            ['name' => 'Youssef Mostafa', 'email' => 'youssef@customer.com', 'phone' => '+201000000001', 'iso' => 'EG'],
            ['name' => 'Dina Mohamed', 'email' => 'dina@customer.com', 'phone' => '+201100000001', 'iso' => 'EG'],
            ['name' => 'Faisal Al-Mutairi', 'email' => 'faisal@customer.com', 'phone' => '+965511111111', 'iso' => 'KW'],
            ['name' => 'Amira Al-Rashid', 'email' => 'amira@customer.com', 'phone' => '+965522222222', 'iso' => 'KW'],
            ['name' => 'Tariq Bin Laden', 'email' => 'tariq@customer.com', 'phone' => '+966533333333', 'iso' => 'SA'],
            ['name' => 'Heba Samir', 'email' => 'heba@customer.com', 'phone' => '+201200000001', 'iso' => 'EG'],
        ];

        foreach ($customers as $c) {
            $iso = $c['iso'];
            $countryId = $countryIds[$iso] ?? null;

            DB::table('customers')->upsert([
                'id' => Str::uuid(),
                'name' => $c['name'],
                'email' => $c['email'],
                'email_verified_at' => now(),
                'phone' => $c['phone'],
                'phone_verified_at' => now(),
                'password' => Hash::make('Customer@123456'),
                'country_id' => $countryId,
                'status' => 'active',
                'loyalty_points' => rand(0, 5000),
                'total_orders' => rand(1, 50),
                'total_spent' => rand(500, 50000) / 100,
                'created_at' => now()->subDays(rand(10, 200)),
                'updated_at' => now(),
            ], ['email'], ['name', 'phone', 'country_id', 'status', 'updated_at']);
        }
    }
}
