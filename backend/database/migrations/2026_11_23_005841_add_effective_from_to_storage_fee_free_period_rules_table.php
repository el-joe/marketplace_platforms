<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client item #11: a free-period rule only applies to storage that started
 * (fbn_storage_fees.stored_since / warehouse_inventories.first_stocked_at)
 * on or after `effective_from`. NULL = applies to all storage, which keeps
 * every existing rule behaving exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_fee_free_period_rules', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('free_days');
        });
    }

    public function down(): void
    {
        Schema::table('storage_fee_free_period_rules', function (Blueprint $table) {
            $table->dropColumn('effective_from');
        });
    }
};
