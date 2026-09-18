<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the PDP/listing-card rotating promo badge (AnimatedBadge) with real
 * data instead of the hardcoded "hello world" placeholders — see
 * docs/plans/dynamic-badges-and-classified-actions.md Task A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promo_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('label_en', 100);
            $table->string('label_ar', 100);
            // Small fixed icon set resolved on the frontend (e.g. "car", "truck").
            $table->string('icon_key', 50);
            $table->string('color_hex', 7)->default('#1a1a2e');
            $table->string('text_color_hex', 7)->default('#FFFFFF');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'sort_order']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_mega_deal')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_mega_deal');
        });

        Schema::dropIfExists('product_promo_badges');
    }
};
