<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // ── Gulf / Middle East ───────────────────────────────────────
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'decimal_places' => 2, 'exchange_rate_to_base' => 3.750000],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2, 'exchange_rate_to_base' => 3.673000],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'decimal_places' => 3, 'exchange_rate_to_base' => 0.307000],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'ر.ق', 'decimal_places' => 2, 'exchange_rate_to_base' => 3.640000],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'ب.د', 'decimal_places' => 3, 'exchange_rate_to_base' => 0.376000],
            ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'ر.ع', 'decimal_places' => 3, 'exchange_rate_to_base' => 0.385000],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'ج.م', 'decimal_places' => 2, 'exchange_rate_to_base' => 48.600000],
            ['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'د.أ', 'decimal_places' => 3, 'exchange_rate_to_base' => 0.709000],
            ['code' => 'MAD', 'name' => 'Moroccan Dirham', 'symbol' => 'د.م.', 'decimal_places' => 2, 'exchange_rate_to_base' => 9.980000],
            ['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'د.ت', 'decimal_places' => 3, 'exchange_rate_to_base' => 3.120000],
            ['code' => 'DZD', 'name' => 'Algerian Dinar', 'symbol' => 'دج', 'decimal_places' => 2, 'exchange_rate_to_base' => 134.900000],
            ['code' => 'LBP', 'name' => 'Lebanese Pound', 'symbol' => 'ل.ل', 'decimal_places' => 0, 'exchange_rate_to_base' => 89750.000000],
            ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'ع.د', 'decimal_places' => 3, 'exchange_rate_to_base' => 1309.000000],
            ['code' => 'YER', 'name' => 'Yemeni Rial', 'symbol' => '﷼', 'decimal_places' => 2, 'exchange_rate_to_base' => 250.300000],

            // ── Major global ──────────────────────────────────────────────
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1.000000],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'exchange_rate_to_base' => 0.920000],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'exchange_rate_to_base' => 0.790000],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0, 'exchange_rate_to_base' => 157.800000],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'exchange_rate_to_base' => 7.240000],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2, 'exchange_rate_to_base' => 83.400000],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1.370000],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1.530000],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr', 'decimal_places' => 2, 'exchange_rate_to_base' => 0.900000],
            ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'decimal_places' => 2, 'exchange_rate_to_base' => 10.420000],
            ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'decimal_places' => 2, 'exchange_rate_to_base' => 10.680000],
            ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'decimal_places' => 2, 'exchange_rate_to_base' => 6.880000],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1.340000],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimal_places' => 2, 'exchange_rate_to_base' => 7.820000],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1.640000],
            ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => 'MX$', 'decimal_places' => 2, 'exchange_rate_to_base' => 17.200000],
            ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'decimal_places' => 2, 'exchange_rate_to_base' => 4.980000],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'decimal_places' => 2, 'exchange_rate_to_base' => 18.650000],
            ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'decimal_places' => 2, 'exchange_rate_to_base' => 32.100000],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'decimal_places' => 2, 'exchange_rate_to_base' => 278.500000],
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'decimal_places' => 2, 'exchange_rate_to_base' => 1560.000000],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'Ksh', 'decimal_places' => 2, 'exchange_rate_to_base' => 129.500000],
        ];

        $now = now();

        foreach ($currencies as $currency) {
            DB::table('currencies')->upsert(
                [
                    'code' => $currency['code'],
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'decimal_places' => $currency['decimal_places'],
                    'base_currency_code' => 'USD',
                    'exchange_rate_to_base' => $currency['exchange_rate_to_base'],
                    'is_active' => true,
                    'is_manually_overridden' => false,
                    'rate_updated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['code'],                                          // unique key
                [
                    'name',
                    'symbol',
                    'decimal_places',
                    'exchange_rate_to_base',
                    'rate_updated_at',
                    'updated_at'
                ] // update on conflict
            );
        }

        $this->command->info('Seeded ' . count($currencies) . ' currencies.');
    }
}
