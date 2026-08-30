<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: warranty_plans.price_cents -> price and
     * warranty_purchases.price_paid_cents -> price_paid were already renamed
     * by the earlier 2026_07_16_180500_rename_remaining_cents_columns
     * migration. This migration is a defensive no-op that only acts if those
     * legacy columns somehow still exist (e.g. on a database that predates
     * that migration), so it is safe to run on any environment.
     */
    public function up(): void
    {
        if (Schema::hasColumn('warranty_plans', 'price_cents') && ! Schema::hasColumn('warranty_plans', 'price')) {
            Schema::table('warranty_plans', function (Blueprint $table) {
                $table->renameColumn('price_cents', 'price');
            });
        }

        if (Schema::hasColumn('warranty_purchases', 'price_paid_cents') && ! Schema::hasColumn('warranty_purchases', 'price_paid')) {
            Schema::table('warranty_purchases', function (Blueprint $table) {
                $table->renameColumn('price_paid_cents', 'price_paid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('warranty_plans', 'price') && ! Schema::hasColumn('warranty_plans', 'price_cents')) {
            Schema::table('warranty_plans', function (Blueprint $table) {
                $table->renameColumn('price', 'price_cents');
            });
        }

        if (Schema::hasColumn('warranty_purchases', 'price_paid') && ! Schema::hasColumn('warranty_purchases', 'price_paid_cents')) {
            Schema::table('warranty_purchases', function (Blueprint $table) {
                $table->renameColumn('price_paid', 'price_paid_cents');
            });
        }
    }
};
