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
        $country = Country::first();
        $admin = Admin::first();

        if (! $country || ! $admin) {
            $this->command->warn('NawiAdsSlotSeeder: needs at least one country and one admin — skipped.');

            return;
        }

        // ── Tier 1: Serious Ad (boost only, no popup, no creative review) ──
        PaidAdSlot::firstOrCreate(
            ['slot_code' => 'listing-boost-serious'],
            [
                'target_type' => PaidAdSlotTargetType::ListingPromotion->value,
                'shows_popup' => false,
                'country_id' => $country->id,
                'name' => 'Serious Ad — Listing Boost',
                'name_ar' => 'إعلان جاد — تعزيز القائمة',
                'pricing_model' => PaidAdSlotPricingModel::FixedMonthly->value,
                'base_rate' => 50000, // placeholder — admin sets real price
                'currency' => 'AED',
                'requires_approval' => false, // boost-only: auto-approve creative
                'max_concurrent' => 255,
                'min_booking_days' => 30,
                'max_booking_days' => 30,
                'is_available' => true,
                'created_by_admin_id' => $admin->id,
            ]
        );

        // ── Tier 2: Serious + Featured (boost + popup, creative needs review) ──
        PaidAdSlot::firstOrCreate(
            ['slot_code' => 'listing-boost-featured'],
            [
                'target_type' => PaidAdSlotTargetType::ListingPromotion->value,
                'shows_popup' => true,
                'country_id' => $country->id,
                'name' => 'Serious + Featured Ad — Listing Boost with Popup',
                'name_ar' => 'إعلان جاد ومميز — تعزيز مع نافذة منبثقة',
                'pricing_model' => PaidAdSlotPricingModel::FixedMonthly->value,
                'base_rate' => 100000, // placeholder — admin sets real price
                'currency' => 'AED',
                'requires_approval' => true, // popup creative needs admin review
                'max_concurrent' => 255,
                'min_booking_days' => 30,
                'max_booking_days' => 30,
                'is_available' => true,
                'created_by_admin_id' => $admin->id,
            ]
        );

        $this->command->info('Nawi Ads slots seeded.');
    }
}
