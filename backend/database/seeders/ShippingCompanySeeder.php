<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds shipping company records and their supervisor login accounts.
 * Covers active and pending statuses so the admin approval queue has demo data.
 *
 * All supervisor accounts use password: password123
 * Fully idempotent — keyed on email with firstOrCreate().
 */
class ShippingCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companiesData = [
            [
                'name'                   => 'Aramex Gulf',
                'email'                  => 'aramex@carrier.com',
                'country_iso'            => 'AE',
                'served_iso_codes'       => ['AE', 'SA', 'KW', 'QA', 'BH'],
                'status'                 => 'active',
                'supervisor_name'        => 'Tariq Supervisor',
                'supervisor_email'       => 'tariq@aramex.com',
                'supervisor_permissions' => ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'],
            ],
            [
                'name'                   => 'Local Express Oman',
                'email'                  => 'localexpress@carrier.com',
                'country_iso'            => 'OM',
                'served_iso_codes'       => ['OM'],
                'status'                 => 'active',
                'supervisor_name'        => 'Salim Al-Balushi',
                'supervisor_email'       => 'salim@localexpress.om',
                'supervisor_permissions' => ['manage_agents', 'view_orders'],
            ],
            [
                'name'                   => 'Cairo Swift Delivery',
                'email'                  => 'cairoswift@carrier.com',
                'country_iso'            => 'EG',
                'served_iso_codes'       => ['EG'],
                'status'                 => 'pending', // approval-queue demo
                'supervisor_name'        => 'Mona Supervisor',
                'supervisor_email'       => 'mona@cairoswift.com',
                'supervisor_permissions' => ['view_orders'],
            ],
        ];

        foreach ($companiesData as $data) {
            $homeCountry = Country::where('iso_code_2', $data['country_iso'])->first();

            $servedCountryIds = Country::whereIn('iso_code_2', $data['served_iso_codes'])
                ->pluck('id')
                ->toArray();

            $company = ShippingCompany::firstOrCreate(
                ['contact_email' => $data['email']],
                [
                    'name'                                    => $data['name'],
                    'country_id'                              => $homeCountry?->id,
                    'contact_email'                           => $data['email'],
                    'served_countries'                        => $servedCountryIds,
                    'status'                                  => $data['status'],
                    'can_supervisors_receive_all_notifications' => true,
                    'approved_at'                             => $data['status'] === 'active' ? now() : null,
                ]
            );

            ShippingCompanySupervisor::firstOrCreate(
                ['email' => $data['supervisor_email']],
                [
                    'shipping_company_id'        => $company->id,
                    'name'                       => $data['supervisor_name'],
                    'password'                   => Hash::make('password123'),
                    'permissions'                => $data['supervisor_permissions'],
                    'is_active'                  => true,
                    'receives_all_notifications' => true,
                ]
            );

            $this->command->line("  ✓ Shipping company: {$data['name']} ({$data['status']})");
        }

        $this->command->info('✅ Shipping companies + supervisors seeded.');
    }
}
