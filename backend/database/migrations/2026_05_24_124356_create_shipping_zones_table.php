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
         * Table: shipping_zones Group of cities with same rates. ColumnTypeDescriptionidUUID PKnameVARCHAR(100)"Cairo Metro", "Upper Egypt"country_idUUID FK → countries.iddescriptionTEXT NULLis_activeBOOLEAN DEFAULT true
         */
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->uuid('country_id')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
