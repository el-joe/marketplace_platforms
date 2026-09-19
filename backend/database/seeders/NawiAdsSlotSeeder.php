<?php

namespace Database\Seeders;

use App\Enums\PaidAdSlotPricingModel;
use App\Enums\PaidAdSlotTargetType;
use App\Models\Admin;
use App\Models\Country;
use App\Models\PaidAdSlot;
use Illuminate\Database\Seeder;

class NawiAdsSlotSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        $countries = Country::all();

        if ($countries->isEmpty() || ! $admin) {
            $this->command->warn('NawiAdsSlotSeeder: needs at least one country and one admin — skipped.');

            return;
        }

        foreach ($countries as $country) {
            if (! $country->currency_code) {
                $this->command->warn("NawiAdsSlotSeeder: country {$country->id} has no currency_code — skipped.");

                continue;
            }

            $suffix = strtolower($country->iso_code_2 ?: (string) $country->id);

            // base_rate values are placeholders in the country's own currency; admin sets real prices.
            // Tier 1: Serious Ad (boost only, no popup, no creative review)
            PaidAdSlot::firstOrCreate(
                ['slot_code' => "listing-boost-serious-{$suffix}", 'country_id' => $country->id],
                [
                    'target_type' => PaidAdSlotTargetType::ListingPromotion->value,
                    'shows_popup' => false,
                    'name' => 'Serious Ad — Listing Boost',
                    'name_ar' => 'إعلان جاد — تعزيز القائمة',
                    'pricing_model' => PaidAdSlotPricingModel::FixedMonthly->value,
                    'base_rate' => 500,
                    'currency' => $country->currency_code,
                    'requires_approval' => false,
                    'max_concurrent' => 255,
                    'min_booking_days' => 30,
                    'max_booking_days' => 30,
                    'is_available' => true,
                    'created_by_admin_id' => $admin->id,
                ]
            );

            // Tier 2: Serious + Featured (boost + popup, creative needs review)
            PaidAdSlot::firstOrCreate(
                ['slot_code' => "listing-boost-featured-{$suffix}", 'country_id' => $country->id],
                [
                    'target_type' => PaidAdSlotTargetType::ListingPromotion->value,
                    'shows_popup' => true,
                    'name' => 'Serious + Featured Ad — Listing Boost with Popup',
                    'name_ar' => 'إعلان جاد ومميز — تعزيز مع نافذة منبثقة',
                    'pricing_model' => PaidAdSlotPricingModel::FixedMonthly->value,
                    'base_rate' => 1000,
                    'currency' => $country->currency_code,
                    'requires_approval' => true,
                    'max_concurrent' => 255,
                    'min_booking_days' => 30,
                    'max_booking_days' => 30,
                    'is_available' => true,
                    'created_by_admin_id' => $admin->id,
                ]
            );
        }

        $this->command->info('Nawi Ads slots seeded.');
    }
}
