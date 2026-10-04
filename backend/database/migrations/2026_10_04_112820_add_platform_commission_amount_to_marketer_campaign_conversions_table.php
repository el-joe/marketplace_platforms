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
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_commission_amount')->default(0)->after('commission_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketer_campaign_conversions', function (Blueprint $table) {
            $table->dropColumn('platform_commission_amount');
        });
    }
};
