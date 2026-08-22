<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $plans = [
            [
                'id' => Str::uuid()->toString(),
                'name_en' => 'Bronze',
                'name_ar' => 'برونز',
                'description_en' => 'Starter plan — up to 10 active listings.',
                'description_ar' => 'خطة مبتدئة — حتى 10 قوائم نشطة.',
                'price' => 5000,    // 50 EGP
                'currency' => 'EGP',
                'billing_cycle' => 'monthly',
                'max_listings' => 10,
                'free_shipping_included' => 0,
                'commission_discount_pct' => 0.00,
                'features' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name_en' => 'Silver',
                'name_ar' => 'فضي',
                'description_en' => 'Growing business — up to 50 listings, 5% commission discount.',
                'description_ar' => 'للمتاجر المتنامية — حتى 50 قائمة، خصم 5% على العمولة.',
                'price' => 20000,   // 200 EGP
                'currency' => 'EGP',
                'billing_cycle' => 'monthly',
                'max_listings' => 50,
                'free_shipping_included' => 0,
                'commission_discount_pct' => 5.00,
                'features' => json_encode(['commission_discount']),
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name_en' => 'Gold',
                'name_ar' => 'ذهبي',
                'description_en' => 'Unlimited listings, 10% commission discount, free shipping included.',
                'description_ar' => 'قوائم غير محدودة، خصم 10% على العمولة، توصيل مجاني.',
                'price' => 50000,   // 500 EGP
                'currency' => 'EGP',
                'billing_cycle' => 'monthly',
                'max_listings' => null,
                'free_shipping_included' => 1,
                'commission_discount_pct' => 10.00,
                'features' => json_encode(['commission_discount', 'free_shipping']),
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name_en' => 'Platinum',
                'name_ar' => 'بلاتيني',
                'description_en' => 'Ultimate plan — unlimited listings, 15% commission discount, free shipping + VIP perks.',
                'description_ar' => 'الخطة المتقدمة — قوائم غير محدودة، خصم 15% على العمولة، توصيل مجاني + مميزات VIP.',
                'price' => 120000,  // 1200 EGP
                'currency' => 'EGP',
                'billing_cycle' => 'monthly',
                'max_listings' => null,
                'free_shipping_included' => 1,
                'commission_discount_pct' => 15.00,
                'features' => json_encode([
                    'commission_discount',
                    'free_shipping',
                    'priority_support',
                    'dedicated_manager',
                    'early_flash_sale_access',
                ]),
                'is_active' => 1,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Upsert by name_en so seeder is idempotent
        foreach ($plans as $plan) {
            DB::table('subscription_plans')->upsert(
                $plan,
                ['name_en'],
                array_keys(\Illuminate\Support\Arr::except($plan, ['id', 'created_at']))
            );
        }
    }
}
