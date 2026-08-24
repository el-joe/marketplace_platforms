<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Groups manual products into tabs for blocks with multiple product lists
    // per block (e.g. mega_deals). Defaults to 0 so existing single-list
    // blocks (product_row manual) keep working unchanged.
    public function up(): void
    {
        if (!Schema::hasColumn('page_block_products', 'tab_index')) {
            Schema::table('page_block_products', function (Blueprint $table) {
                $table->unsignedInteger('tab_index')->default(0)->after('page_block_id');
            });
        }

        $indexes = collect(Schema::getIndexes('page_block_products'))->pluck('name');

        Schema::table('page_block_products', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('page_block_products_page_block_id_product_variant_id_unique')) {
                $table->dropUnique('page_block_products_page_block_id_product_variant_id_unique');
            }
            if (!$indexes->contains('page_block_products_block_tab_variant_unique')) {
                $table->unique(['page_block_id', 'tab_index', 'product_variant_id'], 'page_block_products_block_tab_variant_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_block_products', function (Blueprint $table) {
            $table->dropUnique('page_block_products_block_tab_variant_unique');
            $table->unique(['page_block_id', 'product_variant_id']);
            $table->dropColumn('tab_index');
        });
    }
};
