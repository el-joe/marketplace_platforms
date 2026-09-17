<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-11: packaging supply request costs must be deducted
 * from the vendor's payout exactly once. There was no column to mark a
 * request as already deducted, so PayoutCalculationService had no way to
 * avoid double-charging a vendor across two payout runs. Nullable/no
 * default change needed elsewhere — backfill-safe on a populated table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->timestamp('fee_deducted_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('packaging_supply_requests', function (Blueprint $table) {
            $table->dropColumn('fee_deducted_at');
        });
    }
};
