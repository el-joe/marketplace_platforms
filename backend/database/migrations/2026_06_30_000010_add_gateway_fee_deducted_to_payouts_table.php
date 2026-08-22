<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->bigInteger('gateway_fee_deducted')->default(0)->after('commission')
                ->comment('Sum of sub_orders.gateway_fee for this vendor/period, already netted out of vendor_payout. Shown as a distinct breakdown line.');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('gateway_fee_deducted');
        });
    }
};
