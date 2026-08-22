<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $gateways = [
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'wallet',
                'type'             => 'internal',
                'name'             => 'Platform Wallet',
                'name_ar'          => 'المحفظة',
                'image'            => '/images/gateways/wallet.svg',
                'required_fields'  => json_encode([]),
                'supports_webhook' => false,
                'supports_refund'  => true,
                'is_active'        => true,
                'sort_order'       => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'cod',
                'type'             => 'offline',
                'name'             => 'Cash on Delivery',
                'name_ar'          => 'الدفع عند الاستلام',
                'image'            => '/images/gateways/cod.svg',
                'required_fields'  => json_encode([]),
                'supports_webhook' => false,
                'supports_refund'  => false,
                'is_active'        => true,
                'sort_order'       => 2,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'bank_transfer',
                'type'             => 'offline',
                'name'             => 'Bank Transfer',
                'name_ar'          => 'تحويل بنكي',
                'image'            => '/images/gateways/bank-transfer.svg',
                'required_fields'  => json_encode([
                    ['key' => 'bank_name',              'label' => 'Bank Name',              'label_ar' => 'اسم البنك',           'secret' => false, 'placeholder' => 'e.g. Bank Muscat'],
                    ['key' => 'account_name',           'label' => 'Account Name',           'label_ar' => 'اسم الحساب',          'secret' => false, 'placeholder' => 'Company LLC'],
                    ['key' => 'account_number',         'label' => 'Account Number',         'label_ar' => 'رقم الحساب',          'secret' => false, 'placeholder' => '1234567890'],
                    ['key' => 'iban',                   'label' => 'IBAN',                   'label_ar' => 'IBAN',                 'secret' => false, 'placeholder' => 'OM91BMUS...'],
                    ['key' => 'swift',                  'label' => 'SWIFT / BIC',            'label_ar' => 'سويفت',               'secret' => false, 'placeholder' => 'BMUSOMOMXXX'],
                    ['key' => 'reference_instructions', 'label' => 'Reference Instructions', 'label_ar' => 'تعليمات المرجع',     'secret' => false, 'placeholder' => 'Use order number as reference'],
                ]),
                'supports_webhook' => false,
                'supports_refund'  => false,
                'is_active'        => true,
                'sort_order'       => 3,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'thawani',
                'type'             => 'redirect',
                'name'             => 'Thawani Payment',
                'name_ar'          => 'ثواني للدفع',
                'image'            => '/images/gateways/thawani.svg',
                'required_fields'  => json_encode([
                    ['key' => 'secret_key',       'label' => 'Secret Key',       'label_ar' => 'المفتاح السري',     'secret' => true,  'placeholder' => 'sk_...'],
                    ['key' => 'publishable_key',  'label' => 'Publishable Key',  'label_ar' => 'المفتاح العلني',   'secret' => false, 'placeholder' => 'pk_...'],
                ]),
                'supports_webhook' => true,
                'supports_refund'  => true,
                'is_active'        => true,
                'sort_order'       => 4,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'paytabs',
                'type'             => 'redirect',
                'name'             => 'Paytabs',
                'name_ar'          => 'بي تابس',
                'image'            => '/images/gateways/paytabs.svg',
                'required_fields'  => json_encode([
                    ['key' => 'profile_id',  'label' => 'Profile ID',  'label_ar' => 'معرف الملف الشخصي', 'secret' => false, 'placeholder' => '12345'],
                    ['key' => 'server_key',  'label' => 'Server Key',  'label_ar' => 'مفتاح الخادم',       'secret' => true,  'placeholder' => 'sk_...'],
                    ['key' => 'base_url',    'label' => 'Base URL',    'label_ar' => 'الرابط الأساسي',     'secret' => false, 'placeholder' => 'https://secure.paytabs.com'],
                ]),
                'supports_webhook' => true,
                'supports_refund'  => true,
                'is_active'        => true,
                'sort_order'       => 5,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        DB::table('payment_gateways')->insert($gateways);
    }

    public function down(): void
    {
        DB::table('payment_gateways')->whereIn('code', [
            'wallet', 'cod', 'bank_transfer', 'thawani', 'paytabs',
        ])->delete();
    }
};
