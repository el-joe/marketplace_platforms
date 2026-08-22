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
        if (Schema::hasColumn('delivery_zones', 'base_delivery_fee_cents')
            && ! Schema::hasColumn('delivery_zones', 'base_delivery_fee')) {
            Schema::table('delivery_zones', function (Blueprint $table) {
                $table->renameColumn('base_delivery_fee_cents', 'base_delivery_fee');
            });
        }

        if (Schema::hasColumn('delivery_zones', 'cod_fee_cents')
            && ! Schema::hasColumn('delivery_zones', 'cod_fee')) {
            Schema::table('delivery_zones', function (Blueprint $table) {
                $table->renameColumn('cod_fee_cents', 'cod_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('delivery_zones', 'base_delivery_fee')
            && ! Schema::hasColumn('delivery_zones', 'base_delivery_fee_cents')) {
            Schema::table('delivery_zones', function (Blueprint $table) {
                $table->renameColumn('base_delivery_fee', 'base_delivery_fee_cents');
            });
        }

        if (Schema::hasColumn('delivery_zones', 'cod_fee')
            && ! Schema::hasColumn('delivery_zones', 'cod_fee_cents')) {
            Schema::table('delivery_zones', function (Blueprint $table) {
                $table->renameColumn('cod_fee', 'cod_fee_cents');
            });
        }
    }
};
