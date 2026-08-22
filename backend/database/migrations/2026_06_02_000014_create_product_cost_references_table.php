<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_cost_references', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->uuid('vendor_listing_id')->nullable();
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->nullOnDelete();

            // ── Manufacturer ────────────────────────────────────────────────
            $table->string('manufacturer_name')->nullable();
            $table->string('manufacturer_url', 500)->nullable();
            $table->string('manufacturer_sku', 100)->nullable();

            // ── Cost breakdown (stored as integer cents) ─────────────────────
            $table->unsignedBigInteger('manufacturer_cost_cents')->nullable()->comment('Factory price in smallest currency unit');
            $table->unsignedBigInteger('shipping_cost_cents')->nullable()->comment('Freight / logistics cost');
            $table->unsignedBigInteger('landed_cost_cents')->nullable()->comment('manufacturer_cost + shipping + duties (computed or manual override)');

            // ── Margin ───────────────────────────────────────────────────────
            $table->decimal('platform_margin_pct', 5, 2)->nullable()->comment('Calculated: (selling_price - landed_cost) / selling_price * 100');

            // ── Competitor tracking ──────────────────────────────────────────
            $table->json('competitor_links')->nullable()->comment('Array of {name, url, price_cents, last_checked}');
            $table->timestamp('competitor_last_checked')->nullable();

            // ── Meta ─────────────────────────────────────────────────────────
            $table->text('notes')->nullable();
            $table->tinyInteger('is_confidential')->default(1)->comment('Always 1 — hidden from vendors/customers. Kept for future granularity.');

            $table->uuid('created_by_admin_id');
            $table->foreign('created_by_admin_id')->references('id')->on('admins');

            $table->uuid('updated_by_admin_id')->nullable();
            $table->foreign('updated_by_admin_id')->references('id')->on('admins')->nullOnDelete();

            $table->timestamps();

            $table->index('product_id');
            $table->index('vendor_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_references');
    }
};
