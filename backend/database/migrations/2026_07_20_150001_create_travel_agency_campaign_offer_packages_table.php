<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_agency_campaign_offer_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_campaign_offer_id')
                ->constrained('travel_agency_campaign_offers', indexName: 'ta_c_o_packages_offer_id_foreign')->cascadeOnDelete();
            $table->foreignUuid('travel_package_id')
                ->constrained('travel_packages')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->decimal('commission_override', 5, 2)->nullable()
                ->comment('Per-package commission override — mirrors vendor_campaign_offer_products.commission_override');
            $table->timestamps();

            $table->unique(['travel_agency_campaign_offer_id', 'travel_package_id'], 'ta_c_o_packages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_campaign_offer_packages');
    }
};
