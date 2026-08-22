<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ColumnTypeNullDescriptionidUUID PKNOproduct_idUUID FK→productsNOskuVARCHAR(100) UNIQUENOPlatform-assigned SKUbarcodeVARCHAR(50)YESEAN-13 / UPC-A — for warehouse scanningvariant_nameVARCHAR(255)YESGenerated: Red / Large / 64GBweight_gramsINTYESFor shipping calculationlength_cmDECIMAL(8,2)YESwidth_cmDECIMAL(8,2)YESheight_cmDECIMAL(8,2)YESis_defaultBOOLEANNOWhich variant opens on PDPis_activeBOOLEANNOPlatform can deactivate a variant globallypositionINTNODisplay order in variant selectorcreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNOdeleted_atTIMESTAMPTZYES
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->string('sku', 100)->unique();
            $table->string('barcode', 50)->nullable();
            $table->string('variant_name')->nullable();
            $table->integer('weight_grams')->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
