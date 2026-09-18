<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-6: `warranty_purchases` had no real link to the product being
 * covered — only `orderItem->product_snapshot` JSON captured at checkout,
 * which the API resource used to synthesize a "product" object with no
 * `product_id`, image, slug, or current price. Add a real `product_id`
 * column (mirrors `warranty_claims.product_id`, which already links
 * directly to `products`) and backfill it from each purchase's order item
 * -> product variant -> product, so the purchases API can resolve the live
 * product even when the snapshot is stale/incomplete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->char('product_id', 36)->nullable()->after('order_item_id');
        });

        DB::statement(<<<'SQL'
            UPDATE warranty_purchases wp
            INNER JOIN order_items oi ON oi.id = wp.order_item_id
            INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
            SET wp.product_id = pv.product_id
            WHERE wp.product_id IS NULL
        SQL);

        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_purchases', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
