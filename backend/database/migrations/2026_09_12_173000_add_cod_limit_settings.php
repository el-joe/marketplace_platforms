<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            [
                'id' => (string) Str::uuid(),
                'key' => 'cod_global_max_amount',
                'value' => json_encode(0),
                'category' => 'orders',
                'description' => 'Maximum cart value (platform base currency units) allowed for Cash on Delivery orders. 0 = no limit. Nawi/platform products and global products are exempt.',
                'is_encrypted' => false,
                'is_public' => false,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'key' => 'cod_supermall_max_amount',
                'value' => json_encode(0),
                'category' => 'orders',
                'description' => 'Maximum combined value of Super Mall category items (platform base currency units) allowed for Cash on Delivery orders. 0 = no limit.',
                'is_encrypted' => false,
                'is_public' => false,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'key' => 'cod_supermall_category_id',
                'value' => json_encode(''),
                'category' => 'orders',
                'description' => 'Category UUID identifying the Super Mall category tree, used for the Super Mall COD limit. Empty = not set.',
                'is_encrypted' => false,
                'is_public' => false,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'cod_global_max_amount',
            'cod_supermall_max_amount',
            'cod_supermall_category_id',
        ])->delete();
    }
};
