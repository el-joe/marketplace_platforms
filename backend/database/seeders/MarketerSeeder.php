<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds marketers (company/account records) plus their MarketerAdmin login
 * accounts and a linked MarketerProfile. Covers both marketer types and a
 * spread of global_status values (active/pending/rejected/suspended) so
 * every admin panel filter and queue has realistic demo data.
 *
 * All accounts use password: password123
 * Fully idempotent — keyed on email with firstOrCreate().
 */
class MarketerSeeder extends Seeder
{
    public function run(): void
    {
        $approver = Admin::where('email', 'admin@admin.com')->first();

        $marketersData = [
            [
                'name'            => 'Yasmin Style',
                'email'           => 'yasmin@marketer.com',
                'type'            => 'influencer',
                'country_iso'     => 'AE',
                'niche'           => 'fashion',
                'followers_count' => 250000,
                'engagement_rate' => 4.2,
                'status'          => 'active',
            ],
            [
                'name'            => 'Omar The Tech Guy',
                'email'           => 'omar@marketer.com',
                'type'            => 'influencer',
                'country_iso'     => 'SA',
                'niche'           => 'technology',
                'followers_count' => 180000,
                'engagement_rate' => 5.1,
                'status'          => 'active',
            ],
            [
                'name'            => 'Celebrity Chef Hana',
                'email'           => 'hana@marketer.com',
                'type'            => 'influencer',
                'country_iso'     => 'EG',
                'niche'           => 'food_lifestyle',
                'followers_count' => 1200000,
                'engagement_rate' => 6.8,
                'status'          => 'active',
            ],
            [
                'name'            => 'Budget Deals Affiliate',
                'email'           => 'budgetdeals@marketer.com',
                'type'            => 'affiliate',
                'country_iso'     => 'KW',
                'niche'           => 'general',
                'followers_count' => 15000,
                'engagement_rate' => 2.1,
                'status'          => 'active',
            ],
            [
                'name'            => 'New Applicant Sara',
                'email'           => 'pending-marketer@marketer.com',
                'type'            => 'influencer',
                'country_iso'     => 'AE',
                'niche'           => 'beauty',
                'followers_count' => 45000,
                'engagement_rate' => 3.9,
                'status'          => 'pending', // approval-queue demo
            ],
            [
                'name'            => 'Rejected Account',
                'email'           => 'rejected-marketer@marketer.com',
                'type'            => 'affiliate',
                'country_iso'     => 'EG',
                'niche'           => 'general',
                'followers_count' => 500,
                'engagement_rate' => 0.5,
                'status'          => 'rejected',
            ],
            [
                'name'            => 'Suspended Influencer',
                'email'           => 'suspended-marketer@marketer.com',
                'type'            => 'influencer',
                'country_iso'     => 'SA',
                'niche'           => 'fitness',
                'followers_count' => 90000,
                'engagement_rate' => 4.0,
                'status'          => 'suspended',
            ],
        ];

        foreach ($marketersData as $data) {
            $country = Country::where('iso_code_2', $data['country_iso'])->first();
            $slug    = Str::slug($data['name']);
            $isActive = $data['status'] === 'active';

            $marketer = Marketer::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'                      => $data['name'],
                    'email_verified_at'         => now(),
                    'phone'                     => '+9665' . random_int(10000000, 99999999),
                    'marketer_type'             => $data['type'],
                    'whatsapp_for_campaigns'    => '+9665' . random_int(10000000, 99999999),
                    'global_status'             => $data['status'],
                    'country_id'                => $country?->id,
                    'approved_at'               => $isActive ? now() : null,
                    'approved_by_admin_id'      => $isActive ? $approver?->id : null,
                    'rejection_reason'          => $data['status'] === 'rejected' ? 'Did not meet audience quality requirements.' : null,
                    'onboarding_completed_at'   => $isActive ? now() : null,
                    'total_campaigns'           => 0,
                    'total_conversions'         => fake()->numberBetween(0, 300),
                    'total_earnings'            => fake()->numberBetween(0, 500000),
                ]
            );

            MarketerAdmin::firstOrCreate(
                ['email' => $data['email']],
                [
                    'marketer_id'       => $marketer->id,
                    'name'              => $data['name'],
                    'password'          => Hash::make('password123'),
                    'is_owner'          => true,
                    'is_active'         => $isActive,
                    'email_verified_at' => now(),
                ]
            );

            $marketer->marketerProfile()->firstOrCreate(
                ['marketer_id' => $marketer->id],
                [
                    'bio_en'            => "{$data['niche']} marketer with {$data['followers_count']} followers, ~{$data['engagement_rate']}% engagement.",
                    'bio_ar'            => 'مسوق في مجال ' . $data['niche'],
                    'profile_slug'      => $slug . '-profile',
                    'social_links'      => [
                        'instagram' => 'https://instagram.com/' . $slug,
                    ],
                    'contact_details'   => [
                        'niche'           => $data['niche'],
                        'followers_count' => $data['followers_count'],
                        'engagement_rate' => $data['engagement_rate'],
                    ],
                    'total_campaigns'   => 0,
                    'total_conversions' => fake()->numberBetween(0, 300),
                    'total_earnings'    => fake()->numberBetween(0, 500000),
                ]
            );

            $this->command->line("  ✓ Marketer: {$data['name']} ({$data['type']}, {$data['status']})");
        }

        $this->command->info('✅ Marketers seeded (' . count($marketersData) . ' accounts).');
    }
}
