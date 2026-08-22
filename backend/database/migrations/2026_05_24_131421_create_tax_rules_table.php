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
         * Table: tax_rules ColumnTypeDescriptionidUUID PKnameVARCHAR(100)"Egypt VAT"country_idUUID FK → countries.idregionVARCHAR(100) NULLFor sub-country rulestax_typeENUM('vat','gst','sales_tax')rate_pctDECIMAL(5,2)14.00applies_toENUM('product','shipping','both')category_idUUID FK → categories.id NULLCategory-specific rateprice_includes_taxBOOLEAN DEFAULT falseInclusive vs exclusiveeffective_fromDATEeffective_untilDATE NULL
         */
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->uuid('country_id')->index();
            $table->string('region', 100)->nullable();
            $table->enum('tax_type', ['vat', 'gst', 'sales_tax']);
            $table->decimal('rate_pct', 5, 2);
            $table->enum('applies_to', ['product', 'shipping', 'both']);
            $table->uuid('category_id')->nullable()->index();
            $table->boolean('price_includes_tax')->default(false);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
