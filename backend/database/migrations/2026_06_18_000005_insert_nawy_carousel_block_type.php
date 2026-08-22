<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('block_types')->insert([
            'id'           => Str::uuid()->toString(),
            'code'         => 'nawy_carousel',
            'label_en'     => 'Now Nawy Carousel',
            'label_ar'     => 'كاروسيل Now Nawy',
            'group'        => 'products',
            'icon'         => 'sparkles',
            'description'  => 'Horizontally scrolling carousel of admin-curated products from admin_product_listings',
            'config_schema' => json_encode([
                'fulfillment_filter' => ['type' => 'select', 'options' => ['all', 'express', 'global']],
                'category_id'        => ['type' => 'category_picker'],
                'limit'              => ['type' => 'number', 'default' => 10],
                'title_en'           => ['type' => 'text'],
                'title_ar'           => ['type' => 'text'],
            ]),
            'default_config' => json_encode([
                'fulfillment_filter' => 'all',
                'limit'              => 10,
            ]),
            'is_active'  => 1,
            'sort_order' => 18,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('block_types')->where('code', 'nawy_carousel')->delete();
    }
};
