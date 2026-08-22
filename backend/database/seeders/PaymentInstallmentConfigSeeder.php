<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentInstallmentConfigSeeder extends Seeder
{
    public function run(): void
    {
        // country_payment_methods table removed — BNPL config now managed via
        // payment_gateways seeder. This seeder is kept for reference only.
        if (!Schema::hasTable('country_payment_methods')) {
            return;
        }

        DB::table('country_payment_methods')->where('provider', 'tabby')->update([
            'installments_count'     => 4,
            'installment_label_en'   => 'Pay in {n} interest-free installments of {amount}',
            'installment_label_ar'   => 'ادفع على {n} أقساط بدون فوائد بقيمة {amount}',
            'provider_logo_path'     => '/assets/payment-logos/tabby.svg',
            'updated_at'             => now(),
        ]);

        DB::table('country_payment_methods')->where('provider', 'tamara')->update([
            'installments_count' => 3,
            'updated_at'         => now(),
        ]);
    }
}
