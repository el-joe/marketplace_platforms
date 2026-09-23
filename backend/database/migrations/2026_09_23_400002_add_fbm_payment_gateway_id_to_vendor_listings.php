<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->uuid('fbm_payment_gateway_id')->nullable()->after('fulfillment_model')
                ->comment('For FBM vendors: which gateway customer pays through. Null = platform default.');
            $table->foreign('fbm_payment_gateway_id')->references('id')->on('payment_gateways')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropForeign(['fbm_payment_gateway_id']);
            $table->dropColumn('fbm_payment_gateway_id');
        });
    }
};
