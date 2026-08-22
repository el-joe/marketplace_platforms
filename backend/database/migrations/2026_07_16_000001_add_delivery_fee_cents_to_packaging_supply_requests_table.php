<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_fee_cents')->default(0)->after('total_cost_cents');
        });
    }

    public function down(): void
    {
        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->dropColumn('delivery_fee_cents');
        });
    }
};
