<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client item #19 (WS13): distance-based delivery pricing.
 *
 * One rule per (destination shipping zone, shipping method) — the same key the existing
 * shipping_rates use — so admins configure it next to the zone's rates. Distances are km
 * with one decimal; prices are DECIMAL(19,4) like every other shipping money column.
 *
 * - free_radius_km: delivery is free up to and including this distance (NULL = no free radius).
 * - max_service_radius_km: beyond this the method is unavailable (NULL = no limit).
 * - tiers: [from_km, to_km) → price; to_km NULL = open-ended (last tier only).
 * - categories: optional scope; empty = every product category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_distance_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignUuid('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->decimal('free_radius_km', 8, 1)->nullable();
            $table->decimal('max_service_radius_km', 8, 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'shipping_method_id'], 'shipping_distance_pricing_zone_method_unique');
        });

        Schema::create('shipping_distance_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_distance_pricing_id')->constrained('shipping_distance_pricings')->cascadeOnDelete();
            $table->decimal('from_km', 8, 1);
            $table->decimal('to_km', 8, 1)->nullable();
            $table->decimal('price', 19, 4);
            $table->timestamps();

            $table->index(['shipping_distance_pricing_id', 'from_km'], 'shipping_distance_tiers_pricing_from_index');
        });

        Schema::create('shipping_distance_pricing_categories', function (Blueprint $table) {
            $table->foreignUuid('shipping_distance_pricing_id')->constrained('shipping_distance_pricings', 'id', 'sdp_categories_pricing_fk')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories', 'id', 'sdp_categories_category_fk')->cascadeOnDelete();

            $table->primary(['shipping_distance_pricing_id', 'category_id'], 'sdp_categories_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_distance_pricing_categories');
        Schema::dropIfExists('shipping_distance_tiers');
        Schema::dropIfExists('shipping_distance_pricings');
    }
};
