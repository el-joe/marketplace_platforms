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
        // ColumnTypeNullDescriptionidUUID PKNOproduct_variant_idUUID FK→product_variantsNOattribute_idUUID FK→attributesNOattribute_value_idUUID FK→attribute_valuesYESFor select typesvalue_textVARCHAR(255)YESFor free-text attributesvalue_numberDECIMAL(15,4)YESFor numeric attributes
        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_variant_id')->index();
            $table->uuid('attribute_id')->index();
            $table->uuid('attribute_value_id')->nullable()->index();
            $table->string('value_text_en', 255)->nullable(); // For free-text attributes
            $table->string('value_text_ar', 255)->nullable(); // For free-text attributes
            $table->decimal('value_number', 15, 4)->nullable(); // For numeric attributes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attributes');
    }
};
