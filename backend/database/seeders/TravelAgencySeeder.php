<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use Illuminate\Database\Seeder;

/**
 * Seeds travel agency accounts and their team members.
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
                'members'        => [
                    ['name' => 'Amina Al Farsi', 'email' => 'amina@gulfhorizons.com', 'phone' => '+971501234568', 'role' => 'travel_agency_manager'],
                    ['name' => 'Youssef Hassan', 'email' => 'youssef@gulfhorizons.com', 'phone' => '+971501234569', 'role' => 'travel_agency_staff'],
                ],
            ],
            [
                'name'           => 'Nile Star Tourism',
                'email'          => 'info@nilestar.com',
                'phone'          => '+201001234567',
                'license_number' => 'EGTA-2024-0082',
                'country_iso'    => 'EG',
                'status'         => 'active',
                'members'        => [
                    ['name' => 'Mona Adel', 'email' => 'mona@nilestar.com', 'phone' => '+201001234568', 'role' => 'travel_agency_manager'],
                    ['name' => 'Karim Fathy', 'email' => 'karim@nilestar.com', 'phone' => '+201001234569', 'role' => 'travel_agency_staff'],
                ],
            ],
            [
                'name'           => 'Riyadh Wings Travel',
                'email'          => 'hello@riyadhwings.sa',
                'phone'          => '+966501234567',
                'license_number' => 'SATA-2024-0045',
                'country_iso'    => 'SA',
                'status'         => 'pending',
                'members'        => [
                    ['name' => 'Sara Al Otaibi', 'email' => 'sara@riyadhwings.sa', 'phone' => '+966501234568', 'role' => 'travel_agency_staff'],
                ],
            ],
        ];

        $memberCount = 0;

        foreach ($agencies as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();

            if (!$country) {
                $this->command->warn("Country '{$data['country_iso']}' not found — skipping {$data['email']}");
                continue;
            }

            $agency = TravelAgency::firstOrCreate(
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

            foreach ($data['members'] as $memberData) {
                $member = TravelAgencyMember::firstOrCreate(
                    ['email' => $memberData['email']],
                    [
                        'travel_agency_id' => $agency->id,
                        'name'             => $memberData['name'],
                        'phone'            => $memberData['phone'],
                        'password'         => 'password123',
                        'role'             => $memberData['role'],
                        'is_owner'         => false,
                        'is_active'        => true,
                        'email_verified_at' => now(),
                    ]
                );

                if (!$member->hasRole($memberData['role'])) {
                    $member->syncRoles([$memberData['role']]);
                }

                $memberCount++;
            }
        }

        $this->command->info('TravelAgencySeeder: ' . count($agencies) . " agencies and {$memberCount} members seeded (firstOrCreate).");
    }
}
