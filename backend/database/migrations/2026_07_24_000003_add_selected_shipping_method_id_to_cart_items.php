<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->uuid('selected_shipping_method_id')->nullable()->after('admin_product_listing_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('selected_shipping_method_id')
                ->references('id')->on('shipping_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['selected_shipping_method_id']);
            $table->dropColumn('selected_shipping_method_id');
        });
    }
};
