<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_min_amount')->nullable()->after('commission_rate')
                ->comment('NULL/0 = no floor; final commission = max(calculated, this * qty)');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->dropColumn('commission_min_amount');
        });
    }
};
