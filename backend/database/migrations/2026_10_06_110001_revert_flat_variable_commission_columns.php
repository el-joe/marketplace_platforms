<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the flat variable-commission columns (2026_10_06_000001..3) with the
     * category_commission_tiers table. commission_min_amount stays on
     * marketer_commission_rules (marketer rules use one rate + a floor, no tiers).
     */
    public function up(): void
    {
        $drops = [
            'categories' => ['commission_threshold_price', 'commission_high_rate', 'commission_min_amount'],
            'marketer_commission_rules' => ['commission_threshold_price'],
            'marketer_category_commissions' => ['commission_threshold_price', 'commission_min_amount'],
        ];

        foreach ($drops as $tableName => $columns) {
            foreach ($columns as $col) {
                if (Schema::hasColumn($tableName, $col)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($col));
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'commission_threshold_price')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->bigInteger('commission_threshold_price')->default(0)->after('commission_fbn_fixed')
                    ->comment('0 = tiering disabled; if product_price <= this, high_rate applies; else the normal pct applies');
                $table->decimal('commission_high_rate', 5, 2)->default(0)->after('commission_threshold_price')
                    ->comment('rate applied when product price <= threshold (higher rate for cheap products)');
                $table->bigInteger('commission_min_amount')->default(0)->after('commission_high_rate')
                    ->comment('0 = no floor; final commission = max(calculated, this)');
            });
        }

        if (! Schema::hasColumn('marketer_commission_rules', 'commission_threshold_price')) {
            Schema::table('marketer_commission_rules', function (Blueprint $table) {
                $table->unsignedBigInteger('commission_threshold_price')->nullable()->after('commission_flat_amount')
                    ->comment('NULL = tiering disabled; price threshold in base currency');
            });
        }

        if (! Schema::hasColumn('marketer_category_commissions', 'commission_threshold_price')) {
            Schema::table('marketer_category_commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('commission_threshold_price')->nullable()
                    ->comment('NULL = tiering disabled; price threshold in base currency');
                $table->unsignedBigInteger('commission_min_amount')->nullable()
                    ->comment('NULL/0 = no floor; final commission = max(calculated, this)');
            });
        }
    }
};
