<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartCardOfferSeeder extends Seeder
{
    public function run(): void
    {
        // VERIFY this admin exists in your admins table from AdminSeeder/DatabaseSeeder
        $adminId = DB::table('admins')->where('id', 'a880546a-fb2a-4b1a-9c88-65413830274c')->value('id')
            ?? DB::table('admins')->value('id');

        // Amounts are BIGINT in the country's base currency minor units. No /100, no *100.
        $offersByIso = [
            'AE' => [
                ['card_name' => 'noon one Visa', 'pct' => 5.00, 'min_order' => 10000, 'max_cashback' => 5000],
                ['card_name' => 'noon one Mastercard', 'pct' => 3.00, 'min_order' => 5000, 'max_cashback' => 3000],
            ],
            'SA' => [
                ['card_name' => 'noon one Visa', 'pct' => 5.00, 'min_order' => 10000, 'max_cashback' => 5000],
                ['card_name' => 'noon one Mastercard', 'pct' => 3.00, 'min_order' => 5000, 'max_cashback' => 3000],
            ],
            'EG' => [
                ['card_name' => 'noon one Visa', 'pct' => 5.00, 'min_order' => 100000, 'max_cashback' => 50000],
                ['card_name' => 'noon one Mastercard', 'pct' => 3.00, 'min_order' => 50000, 'max_cashback' => 30000],
            ],
            'KW' => [
                ['card_name' => 'noon one Visa', 'pct' => 5.00, 'min_order' => 1000, 'max_cashback' => 500],
                ['card_name' => 'noon one Mastercard', 'pct' => 3.00, 'min_order' => 500, 'max_cashback' => 300],
            ],
        ];

        foreach ($offersByIso as $iso => $offers) {
            $countryId = DB::table('countries')->where('iso_code_2', $iso)->value('id');

            if (! $countryId) {
                continue;
            }

            foreach ($offers as $index => $offer) {
                $exists = DB::table('cart_card_offers')
                    ->where('country_id', $countryId)
                    ->where('card_name_en', $offer['card_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('cart_card_offers')->insert([
                    'id' => (string) Str::uuid(),
                    'country_id' => $countryId,
                    'card_name_en' => $offer['card_name'],
                    'card_name_ar' => null,
                    'bank_name_en' => null,
                    'bank_name_ar' => null,
                    'card_image_path' => null,
                    'cashback_type' => 'percentage',
                    'cashback_pct' => $offer['pct'],
                    'cashback_fixed_amount' => 0,
                    'label_template_en' => 'Earn {amount} CA$HBACK with {card_name}',
                    'label_template_ar' => null,
                    'apply_url' => null,
                    'apply_label_en' => 'Apply',
                    'apply_label_ar' => 'قدم الآن',
                    'min_order_amount' => $offer['min_order'],
                    'max_cashback_amount' => $offer['max_cashback'],
                    'valid_from' => now(),
                    'valid_until' => now()->addMonths(6),
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_by_admin_id' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
