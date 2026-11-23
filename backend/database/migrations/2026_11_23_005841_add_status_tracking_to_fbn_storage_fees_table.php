<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client item #9: storage fee status changes become traceable and revertible.
 *
 * - payout_id: the vendor payout that deducted this fee (set by
 *   GenerateVendorPayoutsJob). A fee linked to a payout cannot be reverted to
 *   pending from the storage-fees page, or the next payout would deduct it again.
 * - invoiced_at / paid_at: when the status last moved forward.
 *
 * Existing rows are left untouched (all three columns NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_id')->nullable()->after('status')->index();
            $table->timestamp('invoiced_at')->nullable()->after('payout_id');
            $table->timestamp('paid_at')->nullable()->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->dropIndex(['payout_id']);
            $table->dropColumn(['payout_id', 'invoiced_at', 'paid_at']);
        });
    }
};
