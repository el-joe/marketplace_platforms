<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // Only meaningful for type = platform_fbn warehouses; stored on all rows,
            // enforced in application logic.
            $table->unsignedSmallInteger('free_storage_days')->default(30)->after('default_max_capacity_m3');
            $table->bigInteger('daily_fee_per_unit')->default(0)->after('free_storage_days');
            $table->char('daily_fee_currency', 3)->nullable()->after('daily_fee_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['free_storage_days', 'daily_fee_per_unit', 'daily_fee_currency']);
        });
    }
};
