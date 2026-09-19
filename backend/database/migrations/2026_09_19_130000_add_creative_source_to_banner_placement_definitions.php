<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            $table->string('creative_source')->default('upload')->after('supports_vendor_ads');
            $table->json('allowed_destination_types')->nullable()->after('creative_source');
        });

        DB::table('banner_placement_definitions')
            ->whereIn('code', ['product_page_top', 'product_page_bottom'])
            ->update([
                'creative_source' => 'product',
                'allowed_destination_types' => json_encode(['listing']),
            ]);

        DB::table('banner_placement_definitions')
            ->whereIn('code', ['category_top', 'search_top', 'cart_banner'])
            ->update([
                'creative_source' => 'upload',
                'allowed_destination_types' => json_encode(['listing', 'store', 'brand', 'category']),
            ]);
    }

    public function down(): void
    {
        Schema::table('banner_placement_definitions', function (Blueprint $table) {
            $table->dropColumn(['creative_source', 'allowed_destination_types']);
        });
    }
};
