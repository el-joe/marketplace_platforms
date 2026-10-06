<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Originally planned to drop flat variable-commission columns from categories and
     * marketer tables. Those columns were never added (migrations 000001–000003 were
     * rewritten before they ran), so this migration is a safe no-op.
     *
     * Kept in history to preserve the migration sequence; does nothing on up() or down().
     */
    public function up(): void
    {
        // No-op: the flat columns (commission_threshold_price, commission_high_rate,
        // commission_min_amount on categories) were never created. Migration 000001
        // creates category_commission_tiers directly; 000002/000003 add only
        // commission_min_amount to the marketer tables (which should be kept).
    }

    public function down(): void
    {
        // No-op: nothing was added in up().
    }
};
