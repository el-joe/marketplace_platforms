<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'product_variant_id']);
            $table->unique(['customer_id', 'vendor_listing_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'vendor_listing_id']);
            $table->uuid('product_id')->after('customer_id');
            $table->uuid('product_variant_id')->nullable()->after('product_id');
        });
    }
};
