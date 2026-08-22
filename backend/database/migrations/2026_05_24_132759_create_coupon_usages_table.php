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
         * Table: coupon_usages ColumnTypeDescriptionidUUID PKcoupon_idUUID FK → coupons.iduser_idUUID FK → users.idorder_idUUID FK → orders.iddiscount_amount_centsBIGINTWhat was actually deductedused_atTIMESTAMP
         */
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id')->index();
            $table->uuid('customer_id')->index();
            $table->uuid('order_id')->index();
            $table->bigInteger('discount_amount');
            $table->timestamp('used_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
