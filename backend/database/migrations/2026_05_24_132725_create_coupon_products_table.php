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
        /**
         * Table: coupon_products Pivot for product-scoped coupons. ColumnTypeDescriptioncoupon_idUUID FK → coupons.idproduct_idUUID FK → products.id
         */
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->uuid('coupon_id')->index();
            $table->uuid('product_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_products');
    }
};
