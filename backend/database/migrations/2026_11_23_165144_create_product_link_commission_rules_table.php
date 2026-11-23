<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS12 #24: per-country rule that lowers (or waives) the platform commission on a vendor's item when the customer
 * arrived through that vendor's own product share link / QR code (generated in the partner panel).
 *
 *  - fulfillment_models: the sale models the rule applies to (fbm | fbn | cross_dock).
 *  - discount_type: percent | fixed | both | zero. "both" = percent off, then the fixed amount off.
 *  - discount_fixed is per sold unit, in the country's currency (DECIMAL(19,4) like the order money it reduces).
 *  - attribution_window_days: how long after the link click the purchase still qualifies (last touch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_link_commission_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->unique()->constrained('countries')->cascadeOnDelete();
            $table->json('fulfillment_models');
            $table->string('discount_type', 10)->comment('percent | fixed | both | zero');
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('discount_fixed', 19, 4)->default(0)->comment('Per sold unit, country currency');
            $table->unsignedSmallInteger('attribution_window_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_link_commission_rules');
    }
};
