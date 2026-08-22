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
         * Table: shipping_carriers ColumnTypeDescriptionidUUID PKnameVARCHAR(100)"Aramex", "Bosta", "DHL"codeVARCHAR(20) UNIQUEaramexlogo_media_idUUID FK → media.id NULLapi_endpointVARCHAR(255) NULLcredentials_encryptedTEXT NULLAPI keys (encrypted)tracking_url_patternVARCHAR(500)https://aramex.com/track/{tracking_number}supports_codBOOLEAN DEFAULT falsesupports_returnsBOOLEAN DEFAULT trueis_activeBOOLEAN DEFAULT true
         */
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->string('api_endpoint', 255)->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->string('tracking_url_pattern', 500)->nullable();
            $table->boolean('supports_cod')->default(false);
            $table->boolean('supports_returns')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_carriers');
    }
};
