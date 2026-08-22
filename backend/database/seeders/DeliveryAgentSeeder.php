<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DeliveryAgent;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds delivery agent accounts on the 'delivery' guard (delivery_agents table).
 * Mix of platform-employed agents (shipping_company_id = NULL) and third-party
 * agents attached to shipping companies created by ShippingCompanySeeder.
 * Covers all status values and vehicle types.
 *
 * All accounts use password: password123
 * Fully idempotent — keyed on email with firstOrCreate().
 * Run ShippingCompanySeeder before this seeder.
 */
class DeliveryAgentSeeder extends Seeder
{
    public function run(): void
    {
        // ── Platform-employed agents (shipping_company_id explicitly NULL) ─────
        $platformAgentsData = [
            [
                'name'         => 'Mahmoud Rider',
                'email'        => 'mahmoud@delivery.com',
                'country_iso'  => 'EG',
                'vehicle_type' => 'motorcycle',
                'status'       => 'active',
            ],
            [
                'name'         => 'Khalifa Driver',
                'email'        => 'khalifa@delivery.com',
                'country_iso'  => 'AE',
                'vehicle_type' => 'car',
                'status'       => 'on_shift',
            ],
            [
                'name'         => 'Inactive Agent',
                'email'        => 'inactive@delivery.com',
                'country_iso'  => 'SA',
                'vehicle_type' => 'bicycle',
                'status'       => 'inactive',
            ],
            [
                'name'         => 'Suspended Rider',
                'email'        => 'suspended@delivery.com',
                'country_iso'  => 'KW',
                'vehicle_type' => 'motorcycle',
                'status'       => 'suspended',
            ],
        ];

        foreach ($platformAgentsData as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();

            DeliveryAgent::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'                  => $data['name'],
                    'phone'                 => fake()->phoneNumber(),
                    'password'              => 'password123',
                    'country_id'            => $country?->id,
                    'status'                => $data['status'],
                    'agent_type'            => 'platform',
                    'shipping_company_id'   => null, // explicit NULL = platform-employed
                    'added_by_supervisor_id' => null,
                    'vehicle_type'          => $data['vehicle_type'],
                    'is_available'          => $data['status'] === 'on_shift',
                    'rating_avg'            => fake()->randomFloat(2, 3.8, 5.0),
                    'total_deliveries'      => fake()->numberBetween(0, 1000),
                    'per_delivery_fee' => 500,
                ]
            );
        }

        // ── Third-party agents attached to seeded shipping companies ──────────
        $thirdPartyAgentsData = [
            [
                'name'          => 'Aramex Rider 1',
                'email'         => 'aramex-rider1@delivery.com',
                'company_email' => 'aramex@carrier.com',
                'country_iso'   => 'AE',
                'vehicle_type'  => 'van',
                'status'        => 'active',
            ],
            [
                'name'          => 'Aramex Rider 2',
                'email'         => 'aramex-rider2@delivery.com',
                'company_email' => 'aramex@carrier.com',
                'country_iso'   => 'SA',
                'vehicle_type'  => 'motorcycle',
                'status'        => 'on_shift',
            ],
            [
                'name'          => 'Local Express Rider',
                'email'         => 'localexpress-rider1@delivery.com',
                'company_email' => 'localexpress@carrier.com',
                'country_iso'   => 'OM',
                'vehicle_type'  => 'motorcycle',
                'status'        => 'active',
            ],
        ];

        foreach ($thirdPartyAgentsData as $data) {
            $country  = Country::where('iso_code_2', $data['country_iso'])->first();
            $company  = ShippingCompany::where('contact_email', $data['company_email'])->first();

            if (! $company) {
                $this->command->warn("⚠  Shipping company {$data['company_email']} not found — skipping agent {$data['name']}. Run ShippingCompanySeeder first.");
                continue;
            }

            $supervisor = ShippingCompanySupervisor::where('shipping_company_id', $company->id)->first();

            DeliveryAgent::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'                   => $data['name'],
                    'phone'                  => fake()->phoneNumber(),
                    'password'               => Hash::make('password123'),
                    'country_id'             => $country?->id,
                    'status'                 => $data['status'],
                    'agent_type'             => 'third_party',
                    'shipping_company_id'    => $company->id,
                    'added_by_supervisor_id' => $supervisor?->id,
                    'vehicle_type'           => $data['vehicle_type'],
                    'is_available'           => $data['status'] === 'on_shift',
                    'rating_avg'             => fake()->randomFloat(2, 3.8, 5.0),
                    'total_deliveries'       => fake()->numberBetween(0, 1000),
                    'per_delivery_fee' => 500,
                ]
            );
        }

        $this->command->info(
            '✅ Delivery agents seeded ('
            . count($platformAgentsData) . ' platform + '
            . count($thirdPartyAgentsData) . ' third-party).'
        );
    }
}
