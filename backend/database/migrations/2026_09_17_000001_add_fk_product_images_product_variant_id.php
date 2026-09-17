<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-17 task 6: product_images.product_variant_id had no FK,
 * so a deleted variant could leave dangling image rows forever.
 *
 * Backfill-safe: rows that would violate the FK (product_variant_id
 * pointing at a variant that no longer exists) are nulled out first —
 * they fall back to the product-level image set exactly like a legitimate
 * "no variant" row would, instead of blocking the constraint.
 *
 * Operators: on a database with real data, run `php artisan images:audit
 * --fix` first to clear orphan rows (both FKs null) — this migration only
 * clears variant_id values that don't exist any more, it does not touch
 * orphans or the product_id-mismatch rows flagged by that command.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_images')
            ->whereNotNull('product_variant_id')
            ->whereNotIn('product_variant_id', function ($query) {
                $query->select('id')->from('product_variants');
            })
            ->update(['product_variant_id' => null]);

        Schema::table('product_images', function (Blueprint $table) {
            $table->foreign('product_variant_id')
                ->references('id')->on('product_variants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });
    }
};
