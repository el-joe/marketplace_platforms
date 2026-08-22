<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds vendor company records (vendors table) and their login accounts
 * (vendor_admins table). Covers all global_status values so the admin panel
 * approval, suspension, and review queues have demo data.
 *
 * All accounts use password: password123
 * Fully idempotent — keyed on email with firstOrCreate().
 */
class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendorsData = [
            [
                'store_name'    => 'TechZone Electronics',
                'email'         => 'techzone@vendor.com',
                'country_iso'   => 'AE',
                'business_type' => 'llc',
                'global_status' => 'active',
                'commission_rate' => 12.00,
                'owner_name'    => 'Khalid Al-Mansouri',
                'owner_email'   => 'khalid@techzone.com',
            ],
            [
                'store_name'    => 'Bella Fashion House',
                'email'         => 'bella@vendor.com',
                'country_iso'   => 'SA',
                'business_type' => 'sole_prop',
                'global_status' => 'active',
                'commission_rate' => 15.00,
                'owner_name'    => 'Reem Al-Saud',
                'owner_email'   => 'reem@bellafashion.com',
            ],
            [
                'store_name'    => 'Cairo Home Essentials',
                'email'         => 'cairohome@vendor.com',
                'country_iso'   => 'EG',
                'business_type' => 'individual',
                'global_status' => 'under_review', // pending approval demo
                'commission_rate' => 10.00,
                'owner_name'    => 'Ahmed Fathy',
                'owner_email'   => 'ahmed@cairohome.com',
            ],
            [
                'store_name'    => 'Kuwait Gadgets Hub',
                'email'         => 'kuwaitgadgets@vendor.com',
                'country_iso'   => 'KW',
                'business_type' => 'corp',
                'global_status' => 'active',
                'commission_rate' => 11.50,
                'owner_name'    => 'Fahad Al-Otaibi',
                'owner_email'   => 'fahad@kuwaitgadgets.com',
            ],
            [
                'store_name'    => 'Suspended Test Store',
                'email'         => 'suspended@vendor.com',
                'country_iso'   => 'AE',
                'business_type' => 'individual',
                'global_status' => 'suspended', // for suspend/reactivate admin flow testing
                'commission_rate' => 12.00,
                'owner_name'    => 'Test Owner',
                'owner_email'   => 'suspended-owner@vendor.com',
            ],
        ];

        foreach ($vendorsData as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();

            if (! $country) {
                $this->command->warn("⚠  Country {$data['country_iso']} not found — skipping {$data['store_name']}.");
                continue;
            }

            $vendor = Vendor::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'               => $data['owner_name'],
                    'store_name'         => $data['store_name'],
                    'store_slug'         => Str::slug($data['store_name']),
                    'password'           => Hash::make('password123'),
                    'business_type'      => $data['business_type'],
                    'country_id'         => $country->id,
                    'commission_rate'    => $data['commission_rate'],
                    'global_status'      => $data['global_status'],
                    'contact_email'      => $data['email'],
                    'store_rating_avg'   => fake()->randomFloat(2, 3.5, 5.0),
                    'store_rating_count' => fake()->numberBetween(10, 500),
                    'total_sales'        => fake()->randomFloat(2, 1000, 50000),
                    'total_orders'       => fake()->numberBetween(20, 800),
                    // approved_at is a TIMESTAMP (converted from TINYINT by migration 2026_05_31)
                    'approved_at'        => $data['global_status'] === 'active' ? now() : null,
                ]
            );

            // Polymorphic business address (skip if already set)
            if (! $vendor->business_address_id) {
                $city = City::where('country_id', $country->id)->inRandomOrder()->first();

                $address = Address::create([
                    'addressable_type' => Vendor::class,
                    'addressable_id'   => $vendor->id,
                    'recipient_name'   => $data['owner_name'],
                    'country_id'       => $country->id,
                    'city_id'          => $city?->id,
                    'street_address'   => fake()->streetAddress(),
                    'address_type'     => 'both',
                    'is_default'       => true,
                ]);

                $vendor->update(['business_address_id' => $address->id]);
            }

            // Owner login account
            VendorAdmin::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'vendor_id'         => $vendor->id,
                    'name'              => $data['owner_name'],
                    'password'          => Hash::make('password123'),
                    'role'              => 'owner',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );

            // Staff login account (for role-based permission testing)
            VendorAdmin::firstOrCreate(
                ['email' => 'staff-' . $data['email']],
                [
                    'vendor_id'         => $vendor->id,
                    'name'              => 'Staff Member',
                    'password'          => Hash::make('password123'),
                    'role'              => 'staff',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );

            $this->command->line("  ✓ Vendor: {$data['store_name']} ({$data['global_status']})");
        }

        $this->command->info('✅ Vendors + vendor admin accounts seeded.');
    }
}
