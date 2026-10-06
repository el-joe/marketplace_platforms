<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->bigInteger('commission_threshold_price')->default(0)->after('commission_fbn_fixed')
                ->comment('0 = tiering disabled; if product_price <= this, high_rate applies; else the normal pct applies');
            $table->decimal('commission_high_rate', 5, 2)->default(0)->after('commission_threshold_price')
                ->comment('rate applied when product price <= threshold (higher rate for cheap products)');
            $table->bigInteger('commission_min_amount')->default(0)->after('commission_high_rate')
                ->comment('0 = no floor; final commission = max(calculated, this)');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['commission_threshold_price', 'commission_high_rate', 'commission_min_amount']);
        });
    }
};
