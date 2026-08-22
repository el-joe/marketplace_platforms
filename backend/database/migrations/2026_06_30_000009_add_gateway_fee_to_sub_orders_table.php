<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->bigInteger('gateway_fee')->default(0)->after('platform_commission')
                ->comment('Vendor-borne share of payment gateway fee, deducted from vendor_payout. In cents.');
            $table->decimal('gateway_fee_rate', 8, 6)->default(0)->after('gateway_fee')
                ->comment('Effective gateway fee rate applied to this sub-order, stored for audit/recalculation transparency.');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn(['gateway_fee', 'gateway_fee_rate']);
        });
    }
};
