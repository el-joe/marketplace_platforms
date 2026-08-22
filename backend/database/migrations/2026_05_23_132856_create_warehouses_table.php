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
         * warehouses Now country-scoped. ColumnTypeNullDescriptionidUUID PKNOcountry_idUUID FK→countriesNOWhich country this warehouse physically sits innameVARCHAR(150)NO"Cairo Main FBN", "Dubai Hub"codeVARCHAR(20) UNIQUENOCAI-01, DXB-01typeVARCHAR(20)NOplatform_fbn, seller_owned, third_partyowner_seller_idUUID FK→seller_profilesYESNULL for platform-ownedaddress_idUUID FK→addressesNOlatitudeDECIMAL(10,7)YESFor zone matching and delivery estimateslongitudeDECIMAL(10,7)YEStotal_capacity_m3DECIMAL(10,2)YESused_capacity_m3DECIMAL(10,2)NODenormalizedstorage_rate_per_m3_centsBIGINTNODaily FBN storage feestorage_currencyCHAR(3)NOmanager_admin_idUUID FK→usersYESis_activeBOOLEANNOcreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
         */
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->enum('type', ['platform_fbn', 'seller_owned', 'third_party']);
            $table->uuid('owner_vendor_id')->nullable()->index();
            $table->uuid('address_id')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('total_capacity_m3', 10, 2)->nullable();
            $table->decimal('used_capacity_m3', 10, 2)->nullable();
            $table->bigInteger('storage_rate_per_m3_price')->nullable();
            $table->char('storage_currency', 3); // get from country
            $table->uuid('manager_admin_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
