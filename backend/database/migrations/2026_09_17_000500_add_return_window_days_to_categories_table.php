<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-08 task 4: `return_eligible_until` was hardcoded to 14
 * days in two places (AssignmentService.php, Partner/OrderController.php).
 * Backfill-safe: the column is added with a default of 14, so every
 * existing category (populated table) immediately reads 14 — identical to
 * today's hardcoded behaviour — until an admin overrides it per category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('return_window_days')->default(14)->after('min_stock_for_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('return_window_days');
        });
    }
};
