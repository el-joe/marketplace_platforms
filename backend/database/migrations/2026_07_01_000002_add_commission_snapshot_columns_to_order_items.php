<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('commission_fixed_cents')->default(0)
                ->after('commission_rate_pct')
                ->comment('Fixed fee component snapshotted at order time, per unit, in the order\'s currency cents');
            $table->uuid('commission_category_id')->nullable()
                ->after('commission_fixed_cents')
                ->comment('The category from which commission rates were resolved (may be an ancestor if inherited)');
        });
        // commission_fixed_cents backfill: 0 is correct — no fixed fee existed before this change.
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_fixed_cents', 'commission_category_id']);
        });
    }
};
