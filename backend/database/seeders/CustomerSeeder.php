<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds customer accounts on the 'web' guard (customers table).
 * Covers active and suspended statuses for admin flow testing.
 *
 * All accounts use password: password123
 * Fully idempotent — keyed on email with firstOrCreate().
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customersData = [
            [
                'name'        => 'Ali Al-Zahrani',
                'email'       => 'ali@customer.com',
                'country_iso' => 'SA',
                'phone'       => '+966511111111',
            ],
            [
                'name'        => 'Dina Mohamed',
                'email'       => 'dina@customer.com',
                'country_iso' => 'EG',
                'phone'       => '+201100000001',
            ],
            [
                'name'        => 'Rami Hassan',
                'email'       => 'rami@customer.com',
                'country_iso' => 'AE',
                'phone'       => '+971511111111',
            ],
            [
                'name'        => 'Faisal Al-Mutairi',
                'email'       => 'faisal@customer.com',
                'country_iso' => 'KW',
                'phone'       => '+965511111111',
            ],
            [
                'name'        => 'Noura Khalid',
                'email'       => 'noura@customer.com',
                'country_iso' => 'AE',
                'phone'       => '+971522222222',
            ],
            [
                'name'        => 'Suspended Customer',
                'email'       => 'suspended@customer.com',
                'country_iso' => 'SA',
                'phone'       => '+966599999999',
                'status'      => 'suspended', // for suspend/ban admin flow testing
            ],
        ];

        foreach ($customersData as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();

            Customer::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'phone'             => $data['phone'],
                    'password'          => Hash::make('password123'),
                    'country_id'        => $country?->id,
                    'status'            => $data['status'] ?? 'active',
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                    'total_orders'      => fake()->numberBetween(0, 50),
                    'total_spent'       => fake()->randomFloat(2, 0, 5000),
                    'loyalty_points'    => fake()->randomFloat(2, 0, 5000),
                    'referral_code'     => Str::upper(Str::random(8)),
                ]
            );
        }

        $this->command->info('✅ Customers seeded (' . count($customersData) . ' accounts).');
    }
}
