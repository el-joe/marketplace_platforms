<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->boolean('pricing_tiers_enabled')->default(false)->after('currency');
            $table->boolean('show_pricing_tiers_to_customer')->default(true)->after('pricing_tiers_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['pricing_tiers_enabled', 'show_pricing_tiers_to_customer']);
        });
    }
};
