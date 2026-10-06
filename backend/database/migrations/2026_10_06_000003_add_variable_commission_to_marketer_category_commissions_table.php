<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_threshold_price')->nullable()
                ->comment('NULL = tiering disabled; price threshold in base currency');
            $table->unsignedBigInteger('commission_min_amount')->nullable()
                ->comment('NULL/0 = no floor; final commission = max(calculated, this)');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->dropColumn(['commission_threshold_price', 'commission_min_amount']);
        });
    }
};
