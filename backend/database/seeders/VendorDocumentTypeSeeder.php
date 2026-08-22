<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // ── Document types ─────────────────────────────────────────────────
        $types = [
            [
                'code'                => 'business_license',
                'name_en'             => 'Business License',
                'name_ar'             => 'السجل التجاري',
                'description_en'      => 'A valid commercial registration certificate issued by the relevant government authority.',
                'description_ar'      => 'شهادة تسجيل تجاري سارية المفعول صادرة عن الجهة الحكومية المختصة.',
                'accepted_file_types' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_kb'    => 10240,
                'requires_expiry_date'=> true,
                'sort_order'          => 1,
            ],
            [
                'code'                => 'tax_certificate',
                'name_en'             => 'Tax Certificate',
                'name_ar'             => 'الشهادة الضريبية',
                'description_en'      => 'A certificate issued by the tax authority confirming your tax registration status.',
                'description_ar'      => 'شهادة صادرة عن الهيئة الضريبية تُثبت حالة التسجيل الضريبي.',
                'accepted_file_types' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_kb'    => 10240,
                'requires_expiry_date'=> true,
                'sort_order'          => 2,
            ],
            [
                'code'                => 'owner_id',
                'name_en'             => 'Owner ID',
                'name_ar'             => 'هوية المالك',
                'description_en'      => 'A valid national ID or passport for the business owner.',
                'description_ar'      => 'الهوية الوطنية أو جواز السفر ساري المفعول لصاحب المنشأة.',
                'accepted_file_types' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_kb'    => 10240,
                'requires_expiry_date'=> true,
                'sort_order'          => 3,
            ],
            [
                'code'                => 'bank_proof',
                'name_en'             => 'Bank Proof',
                'name_ar'             => 'إثبات الحساب البنكي',
                'description_en'      => 'A bank statement or letter confirming your business account details.',
                'description_ar'      => 'كشف حساب بنكي أو خطاب بنكي يُثبت بيانات الحساب التجاري.',
                'accepted_file_types' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_kb'    => 10240,
                'requires_expiry_date'=> false,
                'sort_order'          => 4,
            ],
            [
                'code'                => 'vat_registration',
                'name_en'             => 'VAT Registration Certificate',
                'name_ar'             => 'شهادة تسجيل ضريبة القيمة المضافة',
                'description_en'      => 'Your VAT registration certificate issued by the tax authority.',
                'description_ar'      => 'شهادة التسجيل في ضريبة القيمة المضافة الصادرة عن الهيئة الضريبية.',
                'accepted_file_types' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_kb'    => 10240,
                'requires_expiry_date'=> true,
                'sort_order'          => 5,
            ],
        ];

        $typeIds = [];
        foreach ($types as $type) {
            $existing = DB::table('vendor_document_types')->where('code', $type['code'])->first();
            if ($existing) {
                $typeIds[$type['code']] = $existing->id;
                continue;
            }

            $id = Str::uuid()->toString();
            $typeIds[$type['code']] = $id;
            DB::table('vendor_document_types')->insert(array_merge($type, [
                'id'         => $id,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ── Per-country requirements ───────────────────────────────────────
        // All 8 countries × all 5 types default to mandatory, preserving
        // today's effective behaviour. Admins loosen specific combinations later.
        $isoCodes = ['SA', 'AE', 'EG', 'KW', 'QA', 'OM', 'BH', 'JO'];

        $countries = DB::table('countries')
            ->whereIn('iso_code_2', $isoCodes)
            ->pluck('id', 'iso_code_2');

        foreach ($countries as $iso => $countryId) {
            foreach ($typeIds as $code => $typeId) {
                $exists = DB::table('vendor_document_country_requirements')
                    ->where('vendor_document_type_id', $typeId)
                    ->where('country_id', $countryId)
                    ->exists();

                if (! $exists) {
                    DB::table('vendor_document_country_requirements')->insert([
                        'id'                      => Str::uuid()->toString(),
                        'vendor_document_type_id' => $typeId,
                        'country_id'              => $countryId,
                        'requirement_level'       => 'mandatory',
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                }
            }
        }
    }
}
