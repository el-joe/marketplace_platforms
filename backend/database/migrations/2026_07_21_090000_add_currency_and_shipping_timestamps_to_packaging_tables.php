<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_supplies', function (Blueprint $table) {
            if (!Schema::hasColumn('packaging_supplies', 'currency')) {
                $table->string('currency', 3)->default(config('app.currency', 'SAR'))->after('unit_cost');
            }
        });

        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('packaging_supply_requests', 'currency')) {
                $table->string('currency', 3)->default(config('app.currency', 'SAR'))->after('delivery_fee');
            }
            if (!Schema::hasColumn('packaging_supply_requests', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('packaging_supply_requests', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            if (Schema::hasColumn('packaging_supply_requests', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('packaging_supply_requests', 'shipped_at')) {
                $table->dropColumn('shipped_at');
            }
            if (Schema::hasColumn('packaging_supply_requests', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('packaging_supplies', function (Blueprint $table) {
            if (Schema::hasColumn('packaging_supplies', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
