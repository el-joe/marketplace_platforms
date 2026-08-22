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
         * ColumnTypeDescriptionidUUID PKuser_idUUID FK → users.idproduct_idUUID FK → products.idproduct_variant_idUUID FK → product_variants.id NULLIf user picked a specific variantadded_atTIMESTAMP
         */
        Schema::create('whishlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->uuid('product_variant_id')->nullable();
            $table->timestamp('added_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whishlists');
    }
};
