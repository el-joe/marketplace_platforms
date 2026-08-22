<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FEES = [
        'packaging_delivery_fee_ae' => 'AED',
        'packaging_delivery_fee_sa' => 'SAR',
        'packaging_delivery_fee_eg' => 'EGP',
        'packaging_delivery_fee_kw' => 'KWD',
        'packaging_delivery_fee_om' => 'OMR',
        'packaging_delivery_fee_qa' => 'QAR',
        'packaging_delivery_fee_bh' => 'BHD',
        'packaging_delivery_fee_jo' => 'JOD',
    ];

    public function up(): void
    {
        foreach (self::FEES as $key => $currency) {
            $attributes = [
                'value' => json_encode(['amount' => 0, 'currency' => $currency]),
                'category' => 'orders',
                'description' => "Packaging supply delivery fee for {$currency} country (smallest currency unit)",
                'is_encrypted' => false,
                'is_public' => false,
                'updated_at' => now(),
            ];

            $updated = DB::table('settings')->where('key', $key)->update($attributes);

            if ($updated === 0) {
                DB::table('settings')->insert(array_merge($attributes, [
                    'id' => (string) Str::uuid(),
                    'key' => $key,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::FEES))->delete();
    }
};
