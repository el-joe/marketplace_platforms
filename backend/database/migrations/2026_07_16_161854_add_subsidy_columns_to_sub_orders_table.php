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
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_subsidy_cents')->default(0)->after('shipping')
                ->comment('Platform-covered share of this sub-order delivery fee. Internal ledger only.');
            $table->unsignedBigInteger('vendor_deduction_cents')->default(0)->after('admin_subsidy_cents')
                ->comment('Vendor-covered share of this sub-order delivery fee, deducted from vendor payout. Internal ledger only.');
            $table->boolean('delivery_subsidy_ledgered')->default(false)->after('vendor_deduction_cents')
                ->comment('Set true once admin_subsidy_cents/vendor_deduction_cents have been posted to ledger_entries, to prevent double-posting.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn(['admin_subsidy_cents', 'vendor_deduction_cents', 'delivery_subsidy_ledgered']);
        });
    }
};
