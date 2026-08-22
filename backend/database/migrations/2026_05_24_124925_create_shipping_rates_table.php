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
         * Table: shipping_rates The pricing matrix. ColumnTypeDescriptionidUUID PKorigin_zone_idUUID FK → shipping_zones.id NULLNULL = any origindestination_zone_idUUID FK → shipping_zones.idshipping_method_idUUID FK → shipping_methods.idcarrier_idUUID FK → shipping_carriers.id NULLNULL = platform defaultbase_fee_centsBIGINTFlat starting pricerate_per_kg_centsBIGINT DEFAULT 0Per kg over basemin_weight_gramsINT DEFAULT 0Thresholdvolumetric_divisorINT DEFAULT 5000For dim weight calcfree_shipping_threshold_centsBIGINT NULLCart total for free shippingcod_extra_fee_centsBIGINT DEFAULT 0Additional COD chargeis_activeBOOLEAN DEFAULT truecreated_at, updated_atTIMESTAMPS
         */
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('origin_zone_id')->nullable()->index();
            $table->uuid('destination_zone_id')->index();
            $table->uuid('shipping_method_id')->index();
            $table->uuid('carrier_id')->nullable()->index();
            $table->bigInteger('base_fee');
            $table->bigInteger('rate_per_kg')->default(0);
            $table->integer('min_weight_grams')->default(0);
            $table->integer('volumetric_divisor')->default(5000);
            $table->bigInteger('free_shipping_threshold')->nullable();
            $table->bigInteger('cod_extra_fee')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
