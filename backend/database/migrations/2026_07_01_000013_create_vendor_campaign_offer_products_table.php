<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_campaign_offer_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_campaign_offer_id')
                ->constrained('vendor_campaign_offers')->cascadeOnDelete();
            $table->foreignUuid('vendor_listing_id')
                ->constrained('vendor_listings')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->decimal('commission_override', 5, 2)->nullable()
                ->comment('Per-listing commission override — mirrors marketer_campaign_products.commission_override');
            $table->timestamps();

            $table->unique(['vendor_campaign_offer_id', 'vendor_listing_id'],'v_c_o_products_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_campaign_offer_products');
    }
};
