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
         * Table: shipping_methods ColumnTypeDescriptionidUUID PKnameVARCHAR(100)"Standard", "Express", "Same-Day"codeVARCHAR(50) UNIQUEdescriptionTEXT NULLmin_delivery_daysINTmax_delivery_daysINTis_activeBOOLEAN DEFAULT true
         */
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->integer('min_delivery_days')->nullable();
            $table->integer('max_delivery_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
