<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TravelAgency;
use Illuminate\Database\Seeder;

/**
 * Seeds travel agency accounts.
 * Fully idempotent — keyed on email with firstOrCreate().
 * All accounts use password: password123
 */
class TravelAgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            [
                'name'           => 'Gulf Horizons Travel',
                'email'          => 'info@gulfhorizons.com',
                'phone'          => '+971501234567',
                'license_number' => 'DTCM-2024-0011',
                'country_iso'    => 'AE',
                'status'         => 'active',
            ],
            [
                'name'           => 'Nile Star Tourism',
                'email'          => 'info@nilestar.com',
                'phone'          => '+201001234567',
                'license_number' => 'EGTA-2024-0082',
                'country_iso'    => 'EG',
                'status'         => 'active',
            ],
            [
                'name'           => 'Riyadh Wings Travel',
                'email'          => 'hello@riyadhwings.sa',
                'phone'          => '+966501234567',
                'license_number' => 'SATA-2024-0045',
                'country_iso'    => 'SA',
                'status'         => 'pending',
            ],
        ];

        foreach ($agencies as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();

            if (!$country) {
                $this->command->warn("Country '{$data['country_iso']}' not found — skipping {$data['email']}");
                continue;
            }

            TravelAgency::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'           => $data['name'],
                    'phone'          => $data['phone'],
                    'license_number' => $data['license_number'],
                    'country_id'     => $country->id,
                    'status'         => $data['status'],
                    'password'       => 'password123',
                ]
            );
        }

        $this->command->info('TravelAgencySeeder: ' . count($agencies) . ' agencies seeded (firstOrCreate).');
    }
}
